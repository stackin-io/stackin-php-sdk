<?php

declare(strict_types=1);

namespace Stackin\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Address;
use Stackin\Br\Product;
use Stackin\Client;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\InvoiceError;
use Stackin\Errors\PaymentRequiredError;
use Stackin\Errors\RateLimitedError;
use Stackin\Invoice;
use Stackin\ValidationResult;

final class ApiFieldsTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $sent = [];

    private function invoice(Response $response): Invoice
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->sent));

        $client = new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private static function json(int $status, array $body): Response
    {
        return new Response($status, [], (string) json_encode($body));
    }

    private function failure(Response $response): ApiError
    {
        try {
            $this->invoice($response)->consultByDps('dps');
        } catch (ApiError $error) {
            return $error;
        }
        $this->fail('expected ApiError');
    }

    public function testStructuredRuleError(): void
    {
        $error = $this->failure(self::json(422, ['detail' => [
            'code' => 'NCM_INVALID',
            'field' => 'items[0].product.br.ncm',
            'message' => 'NCM not in the table',
            'sefaz_rejection' => ['cstat' => '778'],
            'expected' => 'x',
        ]]));

        $this->assertSame('NCM_INVALID', $error->errorCode);
        $this->assertSame('items[0].product.br.ncm', $error->field);
        $this->assertSame(['cstat' => '778'], $error->sefazRejection);
        $this->assertNull($error->retryable);
        $this->assertSame(422, $error->statusCode);
        $this->assertSame('NCM not in the table', $error->detail);
    }

    public function testAuthorityErrorBlock(): void
    {
        $error = $this->failure(self::json(502, [
            'detail' => 'The authority did not answer.',
            'error' => [
                'code' => 'OUTCOME_UNKNOWN',
                'retryable' => false,
                'document_id' => 'DPS123',
            ],
        ]));

        $this->assertSame('OUTCOME_UNKNOWN', $error->errorCode);
        $this->assertFalse($error->retryable);
        $this->assertSame('DPS123', $error->documentId);
        $this->assertSame('The authority did not answer.', $error->detail);
    }

    public function testAuthorityRejectedUsesTheErrorBlockCode(): void
    {
        $error = $this->failure(self::json(422, [
            'detail' => 'Rejected.',
            'code' => 'AUTHORITY_REJECTED',
            'error' => ['code' => '539', 'retryable' => true],
        ]));

        $this->assertSame('539', $error->errorCode);
        $this->assertTrue($error->retryable);
    }

    public function testTopLevelCodeWithoutErrorBlock(): void
    {
        $error = $this->failure(self::json(422, [
            'detail' => 'Rejected.',
            'code' => 'AUTHORITY_REJECTED',
        ]));

        $this->assertSame('AUTHORITY_REJECTED', $error->errorCode);
    }

    public function testDictDetailCode(): void
    {
        $error = $this->failure(self::json(409, ['detail' => [
            'code' => 'RECEIVED_XML_NOT_AVAILABLE',
            'message' => 'Only the summary is stored.',
        ]]));

        $this->assertSame('RECEIVED_XML_NOT_AVAILABLE', $error->errorCode);
        $this->assertSame('Only the summary is stored.', $error->detail);
    }

    public function testPaymentRequiredKeepsItsTypeAndCode(): void
    {
        $error = $this->failure(self::json(402, ['detail' => [
            'code' => 'RECEIVED_PAYMENT_REQUIRED',
            'message' => 'Top up.',
        ]]));

        $this->assertInstanceOf(PaymentRequiredError::class, $error);
        $this->assertSame('RECEIVED_PAYMENT_REQUIRED', $error->errorCode);
    }

    public function testRateLimitedCarriesTheTopLevelCode(): void
    {
        $error = $this->failure(self::json(429, [
            'detail' => 'Wait.',
            'code' => 'DISTRIBUTION_COOLDOWN',
        ]));

        $this->assertInstanceOf(RateLimitedError::class, $error);
        $this->assertSame('DISTRIBUTION_COOLDOWN', $error->errorCode);
    }

    public function testListDetailLeavesEverythingNull(): void
    {
        $error = $this->failure(self::json(422, ['detail' => [
            ['loc' => ['body', 'items'], 'msg' => 'field required', 'type' => 'missing'],
        ]]));

        $this->assertNull($error->errorCode);
        $this->assertNull($error->field);
        $this->assertNull($error->retryable);
        $this->assertNull($error->documentId);
        $this->assertNull($error->sefazRejection);
    }

    public function testPlainStringDetailStaysUntouched(): void
    {
        $error = $this->failure(new Response(401, [], '{"detail":"Invalid token"}'));

        $this->assertSame('Invalid token', $error->detail);
        $this->assertNull($error->errorCode);
    }

    public function testReceivedXmlReturnsRawBytes(): void
    {
        $xml = "<?xml version=\"1.0\"?><nfeProc>\x00\xff{\"not\":\"json\"}</nfeProc>";
        $client = $this->invoice(new Response(200, ['Content-Type' => 'application/xml'], $xml));
        $key = str_repeat('1', 44);

        $this->assertSame($xml, $client->receivedXml($key));
        $request = $this->sent[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            "https://sdk.test/api/v1/received-invoices/{$key}/xml",
            (string) $request->getUri(),
        );
    }

    public function testReceivedXmlNotAvailable(): void
    {
        $client = $this->invoice(self::json(409, ['detail' => [
            'code' => 'RECEIVED_XML_NOT_AVAILABLE',
            'message' => 'Manifest first.',
            'manifestation' => ['method' => 'POST', 'path' => '/x'],
        ]]));

        try {
            $client->receivedXml(str_repeat('1', 44));
            $this->fail('expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(409, $error->statusCode);
            $this->assertSame('RECEIVED_XML_NOT_AVAILABLE', $error->errorCode);
        }
    }

    public function testConsultByDpsEscapesTheIdAndReturnsTheResult(): void
    {
        $client = $this->invoice(self::json(200, ['result' => ['status' => 'cancelled']]));

        $result = $client->consultByDps('DPS 1/2');

        $this->assertSame(['status' => 'cancelled'], $result);
        $this->assertSame(
            'https://sdk.test/api/v1/invoices/dps/DPS%201%2F2',
            (string) $this->sent[0]['request']->getUri(),
        );
    }

    public function testConsultByDpsRefusesADotSegment(): void
    {
        $this->expectException(InvoiceError::class);
        $this->invoice(self::json(200, []))->consultByDps('..');
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(Response $response): array
    {
        $client = $this->invoice($response);
        $result = $client->validate(
            DocumentType::NFSE,
            'Acme',
            '12345678000195',
            [new Product(description: 'Consulting', amount: 100.0)],
            new Address(
                state: 'SC',
                cityCode: '4209102',
                street: 'Rua das Flores',
                number: '1200',
                neighborhood: 'Centro',
                city: 'Joinville',
                zipCode: '89201100',
            ),
        );

        return ['result' => $result];
    }

    public function testValidateValid(): void
    {
        $out = $this->validate(self::json(200, [
            'valid' => true,
            'warnings' => [['code' => 'W1', 'message' => 'careful']],
        ]));

        $result = $out['result'];
        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
        $this->assertSame('W1', $result->warnings[0]['code']);
        $request = $this->sent[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://sdk.test/api/v1/invoices/validate', (string) $request->getUri());
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('NFSE', strtoupper($body['document_type']));
    }

    public function testValidateRulesFailedIsAResult(): void
    {
        $finding = ['code' => 'NCM_INVALID', 'field' => 'items[0].ncm', 'message' => 'bad'];
        $out = $this->validate(self::json(422, [
            'valid' => false,
            'detail' => $finding,
            'errors' => [$finding],
            'warnings' => [],
        ]));

        $result = $out['result'];
        $this->assertFalse($result->valid);
        $this->assertSame([$finding], $result->errors);
    }

    public function testValidatePydanticErrorStillThrows(): void
    {
        $this->expectException(ApiError::class);
        $this->validate(self::json(422, ['detail' => [['loc' => ['body'], 'msg' => 'bad']]]));
    }

    public function testValidateOtherStatusThrows(): void
    {
        try {
            $this->validate(self::json(404, ['detail' => ['code' => 'CLIENT_NOT_FOUND']]));
            $this->fail('expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(404, $error->statusCode);
        }
    }

    public function testValidateChecksLocallyLikeIssue(): void
    {
        $this->expectException(InvoiceError::class);
        $this->invoice(self::json(200, []))->validate(DocumentType::NFSE, 'Acme', '1', []);
    }
}
