<?php

declare(strict_types=1);

namespace Stackin\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Address;
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InvoiceError;
use Stackin\Invoice;

final class InvoiceTest extends TestCase
{
    private function injectMockHttpClient(Invoice $invoice, MockHandler $mock): void
    {
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new HttpClient(['handler' => $handlerStack, 'http_errors' => false]);

        $property = new ReflectionProperty(Invoice::class, 'http');
        $property->setAccessible(true);
        $property->setValue($invoice, $httpClient);
    }

    /**
     * The buyer address NFE issuance requires — every field filled, so tests
     * exercising something other than address validation can pass it through.
     */
    private function validNfeAddress(): Address
    {
        return new Address(
            state: 'SC',
            cityCode: '4209102',
            street: 'Rua das Flores',
            number: '1200',
            neighborhood: 'Centro',
            city: 'Joinville',
            zipCode: '89201100',
        );
    }

    public function testDefaultsToSdkHost(): void
    {
        putenv('STACKIN_BASE_URL');
        $invoice = new Invoice(apiKey: 'key');

        $property = new ReflectionProperty(Invoice::class, 'baseUrl');
        $property->setAccessible(true);

        $this->assertSame(Invoice::DEFAULT_BASE_URL, $property->getValue($invoice));
    }

    public function testUsesExplicitBaseUrl(): void
    {
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com/');

        $property = new ReflectionProperty(Invoice::class, 'baseUrl');
        $property->setAccessible(true);

        $this->assertSame('https://example.com', $property->getValue($invoice));
    }

    public function testIssueRejectsEmptyItems(): void
    {
        $invoice = new Invoice(apiKey: 'key');

        $this->expectException(InvoiceError::class);
        $invoice->issue(DocumentType::NFSE, 'Acme', '123', []);
    }

    public function testIssueRequiresNcmAndCfopForNfe(): void
    {
        $invoice = new Invoice(apiKey: 'key');

        $this->expectException(InvoiceError::class);
        $invoice->issue(
            DocumentType::NFE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0)],
        );
    }

    public function testIssuePostsToInvoicesEndpointWithAuthHeader(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'result' => ['access_key' => 'abc123'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'test-key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $result = $invoice->issue(
            DocumentType::NFE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0, ncm: '12345678', cfop: '5102')],
            $this->validNfeAddress(),
        );

        $request = $mock->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/invoices', $request->getUri()->getPath());
        $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        $this->assertSame('abc123', $result['access_key']);
    }

    public function testRequestReturnsApiErrorOnNon2xx(): void
    {
        $mock = new MockHandler([
            new Response(422, ['Content-Type' => 'application/json'], json_encode([
                'detail' => 'tax_id is invalid',
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        try {
            $invoice->consult('abc123', DocumentType::NFE);
            $this->fail('Expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(422, $error->statusCode);
            $this->assertSame('tax_id is invalid', $error->detail);
        }
    }

    public function testRequestReturnsConnectionFailedErrorOnUnreachableHost(): void
    {
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'http://127.0.0.1:1');

        $this->expectException(ConnectionFailedError::class);
        $invoice->consult('abc123', DocumentType::NFE);
    }

    public function testCancelSendsReasonAndDocumentType(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'result' => ['status' => 'cancelled'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->cancel('abc123', DocumentType::NFSE, 'duplicate issuance');

        $request = $mock->getLastRequest();
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('duplicate issuance', $body['reason']);
        $this->assertSame('nfse', $body['document_type']);
    }

    public function testIssueSendsIdempotencyKeyHeaderWhenGiven(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => ['access_key' => 'abc123'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->issue(
            DocumentType::NFSE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0)],
            idempotencyKey: 'idem-1',
        );

        $request = $mock->getLastRequest();
        $this->assertSame('idem-1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('Bearer key', $request->getHeaderLine('Authorization'));
    }

    public function testIssueOmitsIdempotencyKeyHeaderByDefault(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => ['access_key' => 'abc123'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->issue(
            DocumentType::NFSE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0)],
        );

        $request = $mock->getLastRequest();
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
    }

    public function testIssueKeepsIdempotencyKeyOutOfTheBody(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => ['access_key' => 'abc123'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->issue(
            DocumentType::NFSE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0)],
            idempotencyKey: 'idem-1',
        );

        $body = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertArrayNotHasKey('idempotency_key', $body);
        $this->assertArrayNotHasKey('idempotencyKey', $body);
    }

    public function testReissueSendsIdempotencyKeyHeaderWhenGiven(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_key' => 'reissued-key',
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->reissue('inv-1', idempotencyKey: 'idem-2');

        $request = $mock->getLastRequest();
        $this->assertSame('idem-2', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testInvalidatePostsToTheInvalidationsPath(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'id' => 'range-1',
                'status' => 'invalidated',
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $result = $invoice->invalidate(
            '1',
            10,
            12,
            'Numeracao reservada e nao utilizada por falha no ERP',
        );

        $request = $mock->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/invoices/invalidations', $request->getUri()->getPath());
        $this->assertSame([
            'series' => '1',
            'number_start' => 10,
            'number_end' => 12,
            'reason' => 'Numeracao reservada e nao utilizada por falha no ERP',
        ], json_decode((string) $request->getBody(), true));
        $this->assertSame('invalidated', $result['status']);
    }

    public function testInvalidateRejectsAReasonOutsideTheAllowedLength(): void
    {
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');

        $this->expectException(InvoiceError::class);
        $invoice->invalidate('1', 10, 12, 'curto');
    }

    public function testInvalidateRejectsABackwardsRange(): void
    {
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');

        $this->expectException(InvoiceError::class);
        $invoice->invalidate('1', 12, 10, 'Numeracao reservada e nao utilizada');
    }

    public function testCorrectPostsToTheCorrectionPath(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => ['status' => 'authorized'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $result = $invoice->correct(
            'abc123',
            DocumentType::NFE,
            'Transportadora corrigida para Rapido Ltda',
        );

        $request = $mock->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/invoices/abc123/correction', $request->getUri()->getPath());
        $this->assertSame([
            'document_type' => 'nfe',
            'correction' => 'Transportadora corrigida para Rapido Ltda',
        ], json_decode((string) $request->getBody(), true));
        $this->assertSame('authorized', $result['status']);
    }

    public function testCorrectRejectsTextUnder15Characters(): void
    {
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');

        $this->expectException(InvoiceError::class);
        $invoice->correct('abc123', DocumentType::NFE, 'curto demais');
    }

    public function testCorrectRejectsTextOver1000Characters(): void
    {
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');

        $this->expectException(InvoiceError::class);
        $invoice->correct('abc123', DocumentType::NFE, str_repeat('a', 1001));
    }

    public function testAnUnknownResponseFieldReachesTheCaller(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => [
                    'access_key' => 'ABC',
                    'status' => 'authorized',
                    'field_invented_next_year' => ['nested' => [1, 2]],
                ],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $result = $invoice->consult('ABC', DocumentType::NFSE);

        $this->assertSame('ABC', $result['access_key']);
        $this->assertSame(['nested' => [1, 2]], $result['field_invented_next_year']);
    }

    public function testReissueSendsPostToReissuePath(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_key' => 'reissued-key',
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $result = $invoice->reissue('inv-1');

        $request = $mock->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/invoices/inv-1/reissue', $request->getUri()->getPath());
        $this->assertSame('reissued-key', $result['access_key']);
    }

    public function testReissueReturnsApiErrorOnNon2xx(): void
    {
        $mock = new MockHandler([
            new Response(404, ['Content-Type' => 'application/json'], (string) json_encode([
                'detail' => 'invoice not found',
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        try {
            $invoice->reissue('inv-missing');
            $this->fail('Expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(404, $error->statusCode);
        }
    }

    public function testAddressIsSentAsRecipientAddress(): void
    {
        $body = json_encode(['result' => []]);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $body),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->issue(
            DocumentType::NFE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0, ncm: '12345678', cfop: '5102')],
            $this->validNfeAddress(),
        );

        $body = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertSame('SC', $body['recipient_address']['state']);
        $this->assertSame('4209102', $body['recipient_address']['city_code']);
    }

    public function testIssueRequiresRecipientAddressForNfe(): void
    {
        $invoice = new Invoice(apiKey: 'key');

        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('recipientAddress is required for NFE');
        $invoice->issue(
            DocumentType::NFE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0, ncm: '12345678', cfop: '5102')],
        );
    }

    public function testIssueRejectsPartialRecipientAddressForNfe(): void
    {
        $invoice = new Invoice(apiKey: 'key');

        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('cityCode');
        $invoice->issue(
            DocumentType::NFE,
            'Acme',
            '123',
            [new Product(description: 'Widget', amount: 10.0, ncm: '12345678', cfop: '5102')],
            new Address(state: 'SC'),
        );
    }

    public function testIssueAllowsNfseWithoutRecipientAddress(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => [],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->issue(
            DocumentType::NFSE,
            'Acme',
            '123',
            [new Product(description: 'Servico', amount: 10.0)],
        );

        $this->assertSame('/api/v1/invoices', $mock->getLastRequest()->getUri()->getPath());
    }

    public function testIssueIncludesSeriesAndNumber(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => [],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $invoice->issue(
            DocumentType::NFSE,
            'Acme',
            '123',
            [new Product(description: 'Servico', amount: 10.0)],
            series: '1',
            number: '42',
        );

        $body = json_decode((string) $mock->getLastRequest()->getBody(), true);
        $this->assertSame('1', $body['series']);
        $this->assertSame('42', $body['number']);
    }

    public function testResolveBaseUrlUsesEnvVar(): void
    {
        putenv('STACKIN_BASE_URL=http://env-url:9000');
        $invoice = new Invoice(apiKey: 'key');

        $property = new ReflectionProperty(Invoice::class, 'baseUrl');
        $property->setAccessible(true);

        try {
            $this->assertSame('http://env-url:9000', $property->getValue($invoice));
        } finally {
            putenv('STACKIN_BASE_URL');
        }
    }

    public function testRequestReturnsFullBodyWhenNoResultKey(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'status' => 'ok',
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $result = $invoice->consult('abc123', DocumentType::NFE);

        $this->assertSame(['status' => 'ok'], $result);
    }

    public function testRequestHandlesEmptyResponseBody(): void
    {
        $mock = new MockHandler([new Response(200)]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        $result = $invoice->consult('abc123', DocumentType::NFE);

        $this->assertSame([], $result);
    }

    public function testRequestApiErrorFallsBackToResponseTextWithoutDetail(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'internal error'),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        try {
            $invoice->consult('abc123', DocumentType::NFE);
            $this->fail('Expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(500, $error->statusCode);
            $this->assertSame('internal error', $error->detail);
        }
    }

    public function testRequestApiErrorJsonEncodesNonStringDetail(): void
    {
        $mock = new MockHandler([
            new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'detail' => ['tax_id is invalid', 'cfop is invalid'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $this->injectMockHttpClient($invoice, $mock);

        try {
            $invoice->consult('abc123', DocumentType::NFE);
            $this->fail('Expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(400, $error->statusCode);
            $this->assertSame('["tax_id is invalid","cfop is invalid"]', $error->detail);
        }
    }
}
