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
use Stackin\Br\Product;
use Stackin\Client;
use Stackin\Company;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Invoice;
use Stackin\Items;
use Stackin\ValidationResult;
use TypeError;

final class ActivitySurfaceTest extends TestCase
{
    private const URL = 'https://sdk.test';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @template T of Client
     * @param T $client
     * @param array<array-key, mixed> $body
     * @return T
     */
    private function wire(Client $client, array $body, int $status = 200): Client
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, [], (string) json_encode($body)),
        ]));
        $stack->push(Middleware::history($this->sent));

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $decoded = json_decode((string) $this->sent[0]['request']->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<array-key, mixed> $reply
     * @return array<string, mixed>
     */
    private function issue(array $reply, ?string $acceptLanguage = null, ?string $nbs = null): array
    {
        $invoice = $this->wire(new Invoice(apiKey: 'k', baseUrl: self::URL), $reply);

        return $invoice->issue(
            DocumentType::NFSE,
            'Acme',
            '12345678000195',
            [new Product(description: 'Consulting', unitPrice: 10.0, nbs: $nbs)],
            acceptLanguage: $acceptLanguage,
        );
    }

    public function testProductSendsNbsBesideTheServiceCode(): void
    {
        $this->issue(['result' => ['id' => '1']], nbs: '1.1502.10.00');

        $item = $this->body()['items'][0];
        $this->assertSame('1.1502.10.00', $item['nbs']);
        $this->assertArrayNotHasKey('nbs', $item['product']);
    }

    public function testProductOmitsNbsWhenUnset(): void
    {
        $this->issue(['result' => ['id' => '1']]);

        $this->assertArrayNotHasKey('nbs', $this->body()['items'][0]);
    }

    public function testIssueCarriesTheWarnings(): void
    {
        $warnings = [
            ['code' => 'MEI_ACTIVITY_NOT_ALLOWED', 'message' => 'm', 'field' => 'items[0]'],
            ['code' => 'MEI_NO_ICMS_ACTIVITY', 'message' => 'm', 'field' => null],
        ];

        $reply = ['result' => ['id' => '1', 'status' => 'authorized'], 'warnings' => $warnings];
        $result = $this->issue($reply);

        $this->assertSame('1', $result['id']);
        $this->assertSame($warnings, $result['warnings']);
    }

    public function testIssueWarningsAreAnEmptyListWhenAbsent(): void
    {
        $this->assertSame([], $this->issue(['result' => ['id' => '1']])['warnings']);
        $explicit = $this->issue(['result' => ['id' => '1'], 'warnings' => []]);
        $this->assertSame([], $explicit['warnings']);
    }

    public function testIssueSendsAcceptLanguageAsAHeaderOnly(): void
    {
        $this->issue(['result' => ['id' => '1']], acceptLanguage: 'pt-BR');

        $this->assertSame('pt-BR', $this->sent[0]['request']->getHeaderLine('Accept-Language'));
        $this->assertArrayNotHasKey('accept_language', $this->body());
        $this->assertArrayNotHasKey('acceptLanguage', $this->body());
    }

    public function testIssueOmitsAcceptLanguageByDefault(): void
    {
        $this->issue(['result' => ['id' => '1']]);

        $this->assertFalse($this->sent[0]['request']->hasHeader('Accept-Language'));
    }

    public function testAnEmptyAcceptLanguageIsTreatedAsUnset(): void
    {
        $this->issue(['result' => ['id' => '1']], acceptLanguage: '');

        $this->assertFalse($this->sent[0]['request']->hasHeader('Accept-Language'));
    }

    public function testValidateSendsAcceptLanguageAsAHeaderOnly(): void
    {
        $invoice = $this->wire(
            new Invoice(apiKey: 'k', baseUrl: self::URL),
            ['valid' => true, 'errors' => [], 'warnings' => []],
        );
        $invoice->validate(
            DocumentType::NFSE,
            'Acme',
            '12345678000195',
            [new Product(description: 'Consulting', unitPrice: 10.0)],
            acceptLanguage: 'es',
        );

        $this->assertSame('es', $this->sent[0]['request']->getHeaderLine('Accept-Language'));
        $this->assertArrayNotHasKey('accept_language', $this->body());
        $this->assertArrayNotHasKey('acceptLanguage', $this->body());
    }

    public function testValidateOmitsAcceptLanguageByDefault(): void
    {
        $invoice = $this->wire(
            new Invoice(apiKey: 'k', baseUrl: self::URL),
            ['valid' => true, 'errors' => [], 'warnings' => []],
        );
        $invoice->validate(
            DocumentType::NFSE,
            'Acme',
            '12345678000195',
            [new Product(description: 'Consulting', unitPrice: 10.0)],
        );

        $this->assertFalse($this->sent[0]['request']->hasHeader('Accept-Language'));
    }

    public function testValidateExposesAnActivityWarningWithItsField(): void
    {
        $warning = ['code' => 'ACTIVITY_NOT_REGISTERED', 'message' => 'm', 'field' => 'items[0]'];
        $invoice = $this->wire(
            new Invoice(apiKey: 'k', baseUrl: self::URL),
            ['valid' => true, 'errors' => [], 'warnings' => [$warning]],
        );

        $result = $invoice->validate(
            DocumentType::NFSE,
            'Acme',
            '12345678000195',
            [new Product(description: 'Consulting', unitPrice: 10.0)],
        );

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertSame('ACTIVITY_NOT_REGISTERED', $result->warnings[0]['code']);
        $this->assertSame('items[0]', $result->warnings[0]['field']);
    }

    public function testCatalogItemSendsNbs(): void
    {
        $items = $this->wire(new Items(apiKey: 'k', baseUrl: self::URL), ['id' => 'i'], 201);
        $items->upsert('service', 'Consulting', nbs: '115021000');

        $this->assertSame('115021000', $this->body()['nbs']);
    }

    public function testCatalogItemOmitsNbsWhenUnset(): void
    {
        $items = $this->wire(new Items(apiKey: 'k', baseUrl: self::URL), ['id' => 'i'], 201);
        $items->upsert('service', 'Consulting');

        $this->assertArrayNotHasKey('nbs', $this->body());
    }

    public function testCatalogItemUpdateCarriesNbs(): void
    {
        $items = $this->wire(new Items(apiKey: 'k', baseUrl: self::URL), ['id' => 'i']);
        $items->update('i', ['nbs' => '1.1502.10.00']);

        $this->assertSame(['nbs' => '1.1502.10.00'], $this->body());
    }

    public function testBlockActivityMismatchTrueIsSent(): void
    {
        $company = $this->wire(new Company(apiKey: 'k', baseUrl: self::URL), ['ready' => true]);
        $company->update(blockActivityMismatch: true);

        $this->assertSame(['block_activity_mismatch' => true], $this->body());
    }

    public function testBlockActivityMismatchFalseIsSent(): void
    {
        $company = $this->wire(new Company(apiKey: 'k', baseUrl: self::URL), ['ready' => true]);
        $company->update(blockActivityMismatch: false);

        $this->assertSame(['block_activity_mismatch' => false], $this->body());
    }

    public function testBlockActivityMismatchJoinsTheOtherGroups(): void
    {
        $company = $this->wire(new Company(apiKey: 'k', baseUrl: self::URL), ['ready' => true]);
        $company->update(fiscalFields: ['tax_regime' => '1'], blockActivityMismatch: true);

        $this->assertSame(
            ['block_activity_mismatch' => true, 'fiscal_fields' => ['tax_regime' => '1']],
            $this->body(),
        );
    }

    public function testBlockActivityMismatchRefusesANonBool(): void
    {
        $company = new Company(apiKey: 'k', baseUrl: self::URL);

        $notABool = unserialize('s:4:"true";');

        $this->expectException(TypeError::class);
        $company->update(blockActivityMismatch: $notABool);
    }

    public function testAnEmptyUpdateStillSendsNothing(): void
    {
        $company = $this->wire(new Company(apiKey: 'k', baseUrl: self::URL), ['ready' => true]);
        $company->update();

        $this->assertSame('{}', (string) $this->sent[0]['request']->getBody());
    }

    public function testANonOwnerIsRefusedAs403(): void
    {
        $company = $this->wire(
            new Company(apiKey: 'k', baseUrl: self::URL),
            ['detail' => 'owner only'],
            403,
        );

        $this->expectException(ApiError::class);
        $company->update(blockActivityMismatch: true);
    }
}
