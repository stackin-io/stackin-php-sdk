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
use Stackin\Errors\ItemExistsError;
use Stackin\Errors\ItemInactiveError;
use Stackin\Errors\ItemKindMismatchError;
use Stackin\Errors\ItemNotFoundError;
use Stackin\Errors\ItemTaxRequiredError;
use Stackin\Errors\InvoiceError;
use Stackin\Invoice;
use Stackin\Item;
use Stackin\Items;

final class ItemsTest extends TestCase
{
    private const ITEM_ID = '3f1a9c2e-5b7d-4e08-8a61-2c9d4e7f1b30';
    private const BASE = 'https://sdk.test/api/v1';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @template T of Client
     * @param T $client
     * @param array<string, mixed> $body
     * @return T
     */
    private function wire(
        Client $client,
        array $body = [],
        int $status = 200,
        int $replies = 1,
    ): Client {
        $this->sent = [];
        $responses = [];
        for ($i = 0; $i < $replies; $i++) {
            $text = $status === 204 ? '' : (string) json_encode($body);
            $responses[] = new Response($status, [], $text);
        }
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function items(
        array $body = [],
        int $status = 200,
        ?string $company = null,
        int $replies = 1,
    ): Items {
        return $this->wire(
            new Items(apiKey: 'k', baseUrl: 'https://sdk.test', company: $company),
            $body,
            $status,
            $replies,
        );
    }

    private function request(int $index = 0): Request
    {
        return $this->sent[$index]['request'];
    }

    private function url(int $index = 0): string
    {
        $uri = $this->request($index)->getUri();

        return "{$uri->getScheme()}://{$uri->getHost()}{$uri->getPath()}";
    }

    /** @return array<string, string> */
    private function query(int $index = 0): array
    {
        parse_str($this->request($index)->getUri()->getQuery(), $parsed);

        /** @var array<string, string> $parsed */
        return $parsed;
    }

    /** @return array<string, mixed> */
    private function body(int $index = 0): array
    {
        $decoded = json_decode((string) $this->request($index)->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private const FULL = [
        'id' => self::ITEM_ID,
        'company_id' => 'c0c0c0c0-0000-4000-8000-000000000001',
        'kind' => 'product',
        'description' => 'Cafe torrado 500g',
        'code' => 'CAF-500',
        'unit_price' => 18.9,
        'unit' => 'UN',
        'source' => 'nuvemshop',
        'external_reference' => 'ns-variant-1',
        'notes' => 'n',
        'ncm' => '09012100',
        'cfop' => '5102',
        'tax_situation' => '102',
        'origin' => '0',
        'cest' => '1709600',
        'gtin' => '7891000315507',
        'service_code' => null,
        'iss_retained' => null,
        'is_active' => true,
        'uses_count' => 3,
        'last_used_at' => '2026-11-01T10:00:00Z',
        'created_at' => '2026-10-01T10:00:00Z',
        'updated_at' => '2026-11-02T10:00:00Z',
    ];

    public function testListSendsNoParamsWithoutAFilter(): void
    {
        $this->items()->list();

        self::assertSame('GET', $this->request()->getMethod());
        self::assertSame(self::BASE . '/items', $this->url());
        self::assertSame([], $this->query());
        self::assertSame('', (string) $this->request()->getBody());
    }

    public function testListSendsEveryFilterByItsApiName(): void
    {
        $this->items()->list(
            kind: 'service',
            search: 'cafe',
            source: 'import',
            isActive: false,
            limit: 10,
            offset: 20,
            sortBy: 'unit_price',
            orderBy: 'desc',
        );

        self::assertSame([
            'kind' => 'service',
            'search' => 'cafe',
            'source' => 'import',
            'is_active' => 'false',
            'limit' => '10',
            'offset' => '20',
            'sort_by' => 'unit_price',
            'order_by' => 'desc',
        ], $this->query());
    }

    public function testListReturnsTheEnvelope(): void
    {
        $envelope = ['items' => [self::FULL], 'total' => 1];
        $result = $this->items($envelope)->list();

        self::assertSame($envelope, $result);
    }

    public function testListUnwrapsAResultEnvelope(): void
    {
        $result = $this->items(['result' => ['items' => [], 'total' => 0]])->list();

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    public function testGetReturnsATypedItemWithEveryField(): void
    {
        $item = $this->items(self::FULL)->get(self::ITEM_ID);

        self::assertSame(self::BASE . '/items/' . self::ITEM_ID, $this->url());
        self::assertInstanceOf(Item::class, $item);
        self::assertSame(self::ITEM_ID, $item->id);
        self::assertSame('product', $item->kind);
        self::assertSame('Cafe torrado 500g', $item->description);
        self::assertSame('CAF-500', $item->code);
        self::assertSame(18.9, $item->unitPrice);
        self::assertSame('UN', $item->unit);
        self::assertSame('nuvemshop', $item->source);
        self::assertSame('ns-variant-1', $item->externalReference);
        self::assertSame('09012100', $item->ncm);
        self::assertSame('5102', $item->cfop);
        self::assertSame('102', $item->taxSituation);
        self::assertSame('0', $item->origin);
        self::assertSame('1709600', $item->cest);
        self::assertSame('7891000315507', $item->gtin);
        self::assertNull($item->serviceCode);
        self::assertNull($item->issRetained);
        self::assertTrue($item->isActive);
        self::assertSame(3, $item->usesCount);
        self::assertSame('2026-11-01', $item->lastUsedAt?->format('Y-m-d'));
        self::assertSame('2026-10-01', $item->createdAt?->format('Y-m-d'));
        self::assertSame('2026-11-02', $item->updatedAt?->format('Y-m-d'));
    }

    public function testItemKeepsNullsAndIgnoresUnknownFields(): void
    {
        $item = Item::fromArray(['id' => 'x', 'kind' => 'service', 'new_field' => 1,
            'description' => 'Consultoria', 'iss_retained' => false, 'last_used_at' => null]);

        self::assertNull($item->unitPrice);
        self::assertNull($item->lastUsedAt);
        self::assertFalse($item->issRetained);
    }

    public function testGetEscapesTheId(): void
    {
        $this->items(self::FULL)->get('a/b?c');

        self::assertStringEndsWith('/items/a%2Fb%3Fc', $this->url());
    }

    public function testUpsertSendsOnlyWhatWasPassed(): void
    {
        $saved = $this->items(self::FULL, 201)->upsert(
            kind: 'product',
            description: 'Cafe torrado 500g',
            externalReference: 'ns-variant-1',
            source: 'nuvemshop',
            unitPrice: 18.9,
            issRetained: false,
        );

        self::assertSame('POST', $this->request()->getMethod());
        self::assertSame(self::BASE . '/items', $this->url());
        self::assertSame([
            'kind' => 'product',
            'description' => 'Cafe torrado 500g',
            'unit_price' => 18.9,
            'source' => 'nuvemshop',
            'external_reference' => 'ns-variant-1',
            'iss_retained' => false,
        ], $this->body());
        self::assertTrue($saved->created);
        self::assertSame(self::ITEM_ID, $saved->item->id);
    }

    public function testUpsertExposesTheUpdateAs200(): void
    {
        $saved = $this->items(self::FULL, 200)->upsert(kind: 'product', description: 'x');

        self::assertFalse($saved->created);
        self::assertSame('CAF-500', $saved->item->code);
    }

    public function testEveryMethodSendsTheCompanyHeader(): void
    {
        $this->items(self::FULL)->get(self::ITEM_ID, company: 'acme');
        self::assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));

        $this->items(self::FULL, company: 'acme')->upsert(kind: 'product', description: 'x');
        self::assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));

        $this->items([], 200)->list(company: 'beta');
        self::assertSame('beta', $this->request()->getHeaderLine('X-Stackin-Company'));

        $this->items(self::FULL)->update(self::ITEM_ID, ['notes' => 'a'], company: 'beta');
        self::assertSame('beta', $this->request()->getHeaderLine('X-Stackin-Company'));

        $this->items([], 204)->deactivate(self::ITEM_ID, company: 'beta');
        self::assertSame('beta', $this->request()->getHeaderLine('X-Stackin-Company'));

        $this->items(self::FULL)->reactivate(self::ITEM_ID, company: 'beta');
        self::assertSame('beta', $this->request()->getHeaderLine('X-Stackin-Company'));
    }

    public function testUpdateSendsOnlyTheChangesAndNullClears(): void
    {
        $item = $this->items(self::FULL)->update(
            self::ITEM_ID,
            ['unit_price' => 20.5, 'notes' => null],
        );

        self::assertSame('PATCH', $this->request()->getMethod());
        self::assertSame(self::BASE . '/items/' . self::ITEM_ID, $this->url());
        self::assertSame(['unit_price' => 20.5, 'notes' => null], $this->body());
        self::assertSame(self::ITEM_ID, $item->id);
    }

    public function testUpdateEscapesTheId(): void
    {
        $this->items(self::FULL)->update('a/b', ['notes' => 'x']);

        self::assertStringEndsWith('/items/a%2Fb', $this->url());
    }

    public function testAnEmptyUpdateGoesOutAsAnObject(): void
    {
        $this->items(self::FULL)->update(self::ITEM_ID, []);

        self::assertSame('{}', (string) $this->request()->getBody());
    }

    public function testDeactivateDoesNotDecodeThe204(): void
    {
        $this->items([], 204)->deactivate(self::ITEM_ID);

        self::assertSame('DELETE', $this->request()->getMethod());
        self::assertSame(self::BASE . '/items/' . self::ITEM_ID, $this->url());
        self::assertSame('', (string) $this->request()->getBody());
    }

    public function testDeactivateEscapesTheId(): void
    {
        $this->items([], 204)->deactivate('a/b');

        self::assertStringEndsWith('/items/a%2Fb', $this->url());
    }

    public function testReactivateSendsIsActiveTrue(): void
    {
        $item = $this->items(self::FULL)->reactivate(self::ITEM_ID);

        self::assertSame('PATCH', $this->request()->getMethod());
        self::assertSame(['is_active' => true], $this->body());
        self::assertTrue($item->isActive);
    }

    public function testItemExistsNamesTheItemAndField(): void
    {
        $detail = ['code' => 'ITEM_EXISTS', 'message' => 'm', 'item_id' => self::ITEM_ID,
            'field' => 'code'];
        try {
            $this->items(['detail' => $detail], 409)->update(self::ITEM_ID, ['code' => 'X']);
            self::fail('expected ItemExistsError');
        } catch (ItemExistsError $error) {
            self::assertInstanceOf(ApiError::class, $error);
            self::assertSame(409, $error->statusCode);
            self::assertSame(self::ITEM_ID, $error->itemId);
            self::assertSame('code', $error->field);
        }
    }

    public function testItemNotFound(): void
    {
        $detail = ['code' => 'ITEM_NOT_FOUND', 'message' => 'm', 'item_id' => self::ITEM_ID];
        try {
            $this->items(['detail' => $detail], 404)->get(self::ITEM_ID);
            self::fail('expected ItemNotFoundError');
        } catch (ItemNotFoundError $error) {
            self::assertSame(404, $error->statusCode);
            self::assertSame(self::ITEM_ID, $error->itemId);
        }
    }

    public function testItemInactive(): void
    {
        $detail = ['code' => 'ITEM_INACTIVE', 'item_id' => self::ITEM_ID];
        $this->expectException(ItemInactiveError::class);
        $this->items(['detail' => $detail], 422)->get(self::ITEM_ID);
    }

    public function testItemKindMismatchCarriesTheLine(): void
    {
        $detail = ['code' => 'ITEM_KIND_MISMATCH', 'item_id' => self::ITEM_ID, 'index' => 2];
        try {
            $this->items(['detail' => $detail], 422)->get(self::ITEM_ID);
            self::fail('expected ItemKindMismatchError');
        } catch (ItemKindMismatchError $error) {
            self::assertSame(2, $error->index);
            self::assertSame(self::ITEM_ID, $error->itemId);
        }
    }

    public function testItemTaxRequired(): void
    {
        $detail = ['code' => 'ITEM_TAX_REQUIRED', 'item_id' => self::ITEM_ID];
        try {
            $this->items(['detail' => $detail], 422)->get(self::ITEM_ID);
            self::fail('expected ItemTaxRequiredError');
        } catch (ItemTaxRequiredError $error) {
            self::assertNull($error->index);
            self::assertSame(self::ITEM_ID, $error->itemId);
        }
    }

    public function testAnotherCodeOnTheSameStatusStaysAnApiError(): void
    {
        try {
            $this->items(['detail' => ['code' => 'OTHER']], 409)->get(self::ITEM_ID);
            self::fail('expected ApiError');
        } catch (ApiError $error) {
            self::assertSame(ApiError::class, $error::class);
        }
    }

    public function testIssueLineWithItemIdSendsItAndNeedsNoDescription(): void
    {
        $invoice = $this->wire(
            new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test'),
            ['id' => 'i'],
        );
        $invoice->issue(
            documentType: DocumentType::NFE,
            clientName: 'ACME',
            taxId: '11222333000181',
            items: [new Product(itemId: self::ITEM_ID, amount: 10.0)],
            recipientAddress: new Address(
                street: 'Rua A',
                number: '1',
                neighborhood: 'Centro',
                city: 'Sao Paulo',
                state: 'SP',
                zipCode: '01001000',
                cityCode: '3550308',
            ),
        );

        $line = $this->body()['items'][0];
        self::assertSame(self::ITEM_ID, $line['item_id']);
        self::assertArrayNotHasKey('description', $line);
        // The defaults must not override what the catalog fills.
        self::assertArrayNotHasKey('unit', $line['product'] ?? []);
        self::assertArrayNotHasKey('tax_retained', $line);
        self::assertArrayNotHasKey('br', $line['product'] ?? []);
    }

    public function testLineWithoutItemIdKeepsItsDefaults(): void
    {
        $line = (new Product(description: 'x', ncm: '09012100', cfop: '5102'))->toArray();

        self::assertArrayNotHasKey('item_id', $line);
        self::assertSame('UN', $line['product']['unit']);
        self::assertFalse($line['tax_retained']);
    }

    public function testExplicitLineFieldsStillWinOverTheCatalog(): void
    {
        $line = (new Product(
            itemId: self::ITEM_ID,
            unit: 'KG',
            taxRetained: true,
            description: 'Custom',
        ))->toArray();

        self::assertSame('KG', $line['product']['unit']);
        self::assertTrue($line['tax_retained']);
        self::assertSame('Custom', $line['description']);
    }

    public function testALineNeedsADescriptionOrAnItemId(): void
    {
        $this->expectException(InvoiceError::class);
        new Product(amount: 1.0);
    }

    public function testNfeStillRequiresNcmWithoutAnItemId(): void
    {
        $invoice = new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('ncm is required');
        $invoice->issue(
            documentType: DocumentType::NFE,
            clientName: 'ACME',
            taxId: '11222333000181',
            items: [new Product(description: 'x', amount: 1.0)],
        );
    }
}
