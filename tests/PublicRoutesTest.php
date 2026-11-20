<?php

declare(strict_types=1);

namespace Stackin\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Address;
use Stackin\Br\Product;
use Stackin\Client;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\InvoiceError;
use Stackin\FiscalReference;
use Stackin\Invoice;

/**
 * Stats, readiness, rejection, classification, invalidations, cities
 * and contingency.
 */
final class PublicRoutesTest extends TestCase
{
    private const BASE = 'https://sdk.test/api/v1';
    private const INVOICE_ID = '9f0e1d2c-3b4a-5968-7766-554433221100';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @template T of Client
     * @param T $client
     * @param array<array-key, mixed> $body
     * @return T
     */
    private function mocked(Client $client, array $body = [], int $status = 200): Client
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

    private static function invoice(): Invoice
    {
        return new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
    }

    private static function reference(): FiscalReference
    {
        return new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
    }

    private function path(): string
    {
        return $this->request()->getUri()->getPath();
    }

    private function request(): Request
    {
        $this->assertCount(1, $this->sent);

        return $this->sent[0]['request'];
    }

    /**
     * @return array<string, string>
     */
    private function query(): array
    {
        parse_str($this->request()->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $decoded = json_decode((string) $this->request()->getBody(), true);
        $this->assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function url(): string
    {
        $uri = $this->request()->getUri();

        return $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath();
    }

    public function testStatsWithoutDaysSendsNoQuery(): void
    {
        $this->mocked(self::invoice())->stats();

        $this->assertSame('GET', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/invoices/stats', $this->url());
        $this->assertSame('', $this->request()->getUri()->getQuery());
    }

    public function testStatsSendsDays(): void
    {
        $result = $this->mocked(self::invoice(), ['total' => 3, 'period_days' => 7])
            ->stats(days: 7);

        $this->assertSame(['days' => '7'], $this->query());
        $this->assertSame(3, $result['total']);
    }

    /**
     * @return list<array{int}>
     */
    public static function badDays(): array
    {
        return [[0], [366]];
    }

    #[DataProvider('badDays')]
    public function testStatsRefusesDaysOutsideTheApiBounds(int $days): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('days must be 1 to 365');

        $this->mocked(self::invoice())->stats(days: $days);
    }

    public function testStatsCompanyNamesTheHeader(): void
    {
        $this->mocked(self::invoice())->stats(company: 'acme');

        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
    }

    public function testReadinessReadsTheCompany(): void
    {
        $result = $this->mocked(self::invoice(), ['ready' => false, 'missing' => ['certificate']])
            ->readiness(company: 'acme');

        $this->assertSame('GET', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/company', $this->url());
        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
        $this->assertFalse($result['ready']);
        $this->assertSame(['certificate'], $result['missing']);
    }

    public function testRejectionReadsByInvoiceId(): void
    {
        $result = $this->mocked(self::invoice(), ['code' => '539', 'steps' => ['a']])
            ->rejection(self::INVOICE_ID, company: 'acme');

        $this->assertSame('GET', $this->request()->getMethod());
        $this->assertSame(
            self::BASE . '/invoices/' . self::INVOICE_ID . '/rejection',
            $this->url(),
        );
        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
        $this->assertSame('539', $result['code']);
    }

    public function testRejectionOfADocumentNotRejectedIsAnApiError(): void
    {
        $client = $this->mocked(self::invoice(), ['detail' => 'Invoice is not rejected.'], 409);

        try {
            $client->rejection(self::INVOICE_ID);
            $this->fail('expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(409, $error->statusCode);
        }
    }

    public function testInvoiceIdStaysOneSegment(): void
    {
        $this->mocked(self::invoice())->rejection('a/b');
        $this->assertSame('/api/v1/invoices/a%2Fb/rejection', $this->path());

        $this->mocked(self::invoice(), [])->submissions('a/b');
        $this->assertSame('/api/v1/invoices/a%2Fb/submissions', $this->path());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function dotSegments(): array
    {
        return [
            ['rejection', '.'],
            ['rejection', '..'],
            ['submissions', '.'],
            ['submissions', '..'],
        ];
    }

    #[DataProvider('dotSegments')]
    public function testDotSegmentsAreRefused(string $method, string $id): void
    {
        $client = $this->mocked(self::invoice());

        try {
            $client->{$method}($id);
            $this->fail('expected InvoiceError');
        } catch (InvoiceError) {
            $this->assertSame([], $this->sent);
        }
    }

    public function testClassifySendsTheDescriptionAndContext(): void
    {
        $client = $this->mocked(self::invoice(), ['primary_candidate' => ['ncm' => '22030000']]);
        $result = $client->classify(
            'Cerveja artesanal IPA 500ml',
            DocumentType::NFE,
            recipientState: 'sp',
            answers: ['purpose' => 'resale'],
            company: 'acme',
        );

        $this->assertSame('POST', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/ai/classify', $this->url());
        $this->assertSame([
            'description' => 'Cerveja artesanal IPA 500ml',
            'document_type' => 'nfe',
            'recipient_state' => 'SP',
            'answers' => ['purpose' => 'resale'],
        ], $this->body());
        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
        $this->assertSame(['ncm' => '22030000'], $result['primary_candidate']);
    }

    public function testClassifyLeavesOutWhatWasNotPassed(): void
    {
        $this->mocked(self::invoice())->classify('Consultoria', DocumentType::NFSE);

        $this->assertSame(
            ['description' => 'Consultoria', 'document_type' => 'nfse'],
            $this->body(),
        );
    }

    /**
     * @return list<array{string, ?string, string}>
     */
    public static function badClassifications(): array
    {
        return [
            ['ab', null, 'description'],
            [str_repeat('a', 501), null, 'description'],
            ['Cerveja', 'SPX', 'recipientState'],
        ];
    }

    #[DataProvider('badClassifications')]
    public function testClassifyRefusesLocally(
        string $description,
        ?string $state,
        string $message,
    ): void {
        $client = $this->mocked(self::invoice());

        try {
            $client->classify($description, DocumentType::NFE, recipientState: $state);
            $this->fail('expected InvoiceError');
        } catch (InvoiceError $error) {
            $this->assertStringContainsString($message, $error->getMessage());
            $this->assertSame([], $this->sent);
        }
    }

    public function testInvalidationsListsAPage(): void
    {
        $result = $this->mocked(self::invoice(), ['items' => [], 'total' => 0])->invalidations(
            limit: 20,
            offset: 40,
            sortBy: 'created_at',
            orderBy: 'asc',
            company: 'acme',
        );

        $this->assertSame('GET', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/invoices/invalidations', $this->url());
        $this->assertSame(
            ['limit' => '20', 'offset' => '40', 'sort_by' => 'created_at', 'order_by' => 'asc'],
            $this->query(),
        );
        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
        $this->assertSame(0, $result['total']);
    }

    public function testInvalidationsWithoutArgumentsSendsNoQuery(): void
    {
        $this->mocked(self::invoice())->invalidations();

        $this->assertSame('', $this->request()->getUri()->getQuery());
    }

    public function testCitiesSearchesByName(): void
    {
        $rows = [['country' => 'BR', 'code' => '3544004', 'name' => 'Santa Rita', 'state' => 'SP']];
        $result = $this->mocked(self::reference(), $rows)
            ->cities('santa rita', state: 'sp');

        $this->assertSame('GET', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/cities', $this->url());
        $this->assertSame(
            ['name' => 'santa rita', 'country' => 'BR', 'state' => 'SP'],
            $this->query(),
        );
        $this->assertSame($rows, $result);
    }

    public function testCitiesTakesTheClientCountryOrAnOverride(): void
    {
        $reference = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test', country: 'PT');

        $this->mocked($reference, [])->cities('lisboa');
        $this->assertSame(['name' => 'lisboa', 'country' => 'PT'], $this->query());

        $this->mocked($reference, [])->cities('lisboa', country: 'BR');
        $this->assertSame('BR', $this->query()['country']);
    }

    /**
     * @return list<array{string, ?string, string}>
     */
    public static function badCities(): array
    {
        return [
            ['a', null, 'name'],
            [str_repeat('a', 101), null, 'name'],
            ['santa rita', 'S', 'state'],
        ];
    }

    #[DataProvider('badCities')]
    public function testCitiesRefusesLocally(string $name, ?string $state, string $message): void
    {
        $client = $this->mocked(self::reference());

        try {
            $client->cities($name, state: $state);
            $this->fail('expected InvoiceError');
        } catch (InvoiceError $error) {
            $this->assertStringContainsString($message, $error->getMessage());
            $this->assertSame([], $this->sent);
        }
    }

    public function testCitiesRefusesANonListResponse(): void
    {
        $this->expectException(InvoiceError::class);

        $this->mocked(self::reference(), ['code' => 'x'])
            ->cities('santa rita');
    }

    private static function nfse(): Product
    {
        return new Product(description: 'Consultoria', amount: 100.0);
    }

    private static function nfeItem(): Product
    {
        return new Product(
            description: 'Cerveja',
            amount: 10.0,
            ncm: '22030000',
            cfop: '5102',
        );
    }

    public function testIssueSendsContingencyWhenTrue(): void
    {
        $address = new Address(
            street: 'Rua A',
            number: '1',
            neighborhood: 'Centro',
            city: 'Sao Paulo',
            state: 'SP',
            zipCode: '01001000',
        );
        $this->mocked(self::invoice(), ['id' => 'x'])->issue(
            DocumentType::NFE,
            'Cliente',
            '00000000000191',
            [self::nfeItem()],
            recipientAddress: $address,
            contingency: true,
        );

        $this->assertTrue($this->body()['contingency']);
    }

    public function testIssueLeavesContingencyOutByDefault(): void
    {
        $this->mocked(self::invoice(), ['id' => 'x'])
            ->issue(DocumentType::NFSE, 'Cliente', '00000000000191', [self::nfse()]);

        $this->assertArrayNotHasKey('contingency', $this->body());
    }
}
