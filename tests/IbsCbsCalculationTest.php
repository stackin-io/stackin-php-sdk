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
use Stackin\Client;
use Stackin\Errors\ApiError;
use Stackin\Errors\InvoiceError;
use Stackin\FiscalReference;
use Stackin\IbsCbsCalculationItem;
use Stackin\IbsCbsRates;

final class IbsCbsCalculationTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @param array<array-key, mixed> $body
     */
    private function client(array $body = [], int $status = 200): FiscalReference
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, [], (string) json_encode($body)),
        ]));
        $stack->push(Middleware::history($this->sent));

        $client = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /** @return array<string, mixed> */
    private function sentBody(): array
    {
        $this->assertCount(1, $this->sent);

        /** @var array<string, mixed> */
        return json_decode((string) $this->sent[0]['request']->getBody(), true);
    }

    /** @return array<string, mixed> */
    private static function answer(): array
    {
        $amounts = [
            'base' => '100.00',
            'ibs_state' => '0.10',
            'ibs_city' => '0.00',
            'ibs' => '0.10',
            'cbs' => '0.90',
            'deferred_ibs_state' => '0.00',
            'deferred_ibs_city' => '0.00',
            'deferred_cbs' => '0.00',
        ];
        $rates = ['rate_state' => '0.1', 'rate_city' => '0', 'rate_federal' => '0.9'];

        return [
            'simulated' => true,
            'notice' => 'Simulation only.',
            'document_type' => 'nfe',
            'operation_date' => '2026-06-01',
            'something_new' => 1,
            'items' => [[
                'cst' => '000',
                'classification' => '000001',
                'indicators' => ['group' => 0, 'reduction' => null, 'deferral' => null],
                'has_group' => true,
                'rates' => $rates,
                'rates_source' => 'official_2026',
                'reduction_ibs' => null,
                'reduction_cbs' => null,
                'effective_rates' => $rates,
                'deferral' => null,
                'amounts' => $amounts,
            ]],
            'total' => $amounts,
        ];
    }

    public function testTheBodyCarriesOnlyWhatWasSet(): void
    {
        $this->client(self::answer())->calculateIbsCbs(
            [new IbsCbsCalculationItem(cst: '000', classification: '000001', amount: 100.0)],
            '2026-06-01',
        );

        $request = $this->sent[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/ibs-cbs/calculate', $request->getUri()->getPath());
        $this->assertEquals(
            [
                'document_type' => 'nfe',
                'operation_date' => '2026-06-01',
                'items' => [['cst' => '000', 'classification' => '000001', 'amount' => 100.0]],
            ],
            $this->sentBody(),
        );
    }

    public function testArrayItemsRatesAndDocumentTypeAreSent(): void
    {
        $this->client(self::answer())->calculateIbsCbs(
            [['cst' => '200', 'classification' => '200009', 'base' => 50, 'reduction_ibs' => 60]],
            '2027-01-10',
            'nfse',
            new IbsCbsRates(rateState: 0.1, rateFederal: 0.9),
        );

        $this->assertEquals(
            [
                'document_type' => 'nfse',
                'operation_date' => '2027-01-10',
                'items' => [[
                    'cst' => '200',
                    'classification' => '200009',
                    'base' => 50.0,
                    'reduction_ibs' => 60.0,
                ]],
                'rates' => ['rate_state' => 0.1, 'rate_federal' => 0.9],
            ],
            $this->sentBody(),
        );
    }

    public function testEmptyRatesAreLeftOut(): void
    {
        $this->client(self::answer())->calculateIbsCbs(
            [['cst' => '000', 'classification' => '000001', 'amount' => 1]],
            '2026-06-01',
            rates: [],
        );

        $this->assertArrayNotHasKey('rates', $this->sentBody());
    }

    public function testTheCompanyHeaderIsSent(): void
    {
        $this->client(self::answer())->calculateIbsCbs(
            [['cst' => '000', 'classification' => '000001', 'amount' => 1]],
            '2026-06-01',
            company: 'co-1',
        );

        $this->assertSame('co-1', $this->sent[0]['request']->getHeaderLine('X-Stackin-Company'));
    }

    public function testTheResponseIsTyped(): void
    {
        $result = $this->client(self::answer())->calculateIbsCbs(
            [['cst' => '000', 'classification' => '000001', 'amount' => 100]],
            '2026-06-01',
        );

        $this->assertTrue($result->simulated);
        $this->assertSame('Simulation only.', $result->notice);
        $this->assertSame('nfe', $result->documentType);
        $this->assertSame('2026-06-01', $result->operationDate);
        $this->assertCount(1, $result->items);

        $item = $result->items[0];
        $this->assertSame('000001', $item->classification);
        $this->assertSame(0, $item->indicators->group);
        $this->assertNull($item->indicators->reduction);
        $this->assertTrue($item->hasGroup);
        $this->assertSame('official_2026', $item->ratesSource);
        $this->assertSame('0.9', $item->rates?->rateFederal);
        $this->assertSame('0.1', $item->effectiveRates?->rateState);
        $this->assertNull($item->reductionIbs);
        $this->assertNull($item->deferral);
        $this->assertSame('0.90', $item->amounts->cbs);
        $this->assertSame('100.00', $result->total->base);
        $this->assertSame('0.10', $result->total->ibs);
        $this->assertSame('0.00', $result->total->deferredCbs);
    }

    public function testAnItemWithoutRatesDecodesToNull(): void
    {
        $answer = self::answer();
        $answer['items'][0]['rates'] = null;
        $answer['items'][0]['rates_source'] = null;
        $answer['items'][0]['effective_rates'] = null;

        $result = $this->client($answer)->calculateIbsCbs(
            [['cst' => '000', 'classification' => '000001', 'amount' => 100]],
            '2026-06-01',
        );

        $this->assertNull($result->items[0]->rates);
        $this->assertNull($result->items[0]->ratesSource);
        $this->assertNull($result->items[0]->effectiveRates);
    }

    public function testAnApiRefusalIsAnApiError(): void
    {
        $client = $this->client(
            ['detail' => ['code' => 'IBS_CBS_RATES_REQUIRED', 'field' => 'rates']],
            422,
        );

        $this->expectException(ApiError::class);

        $client->calculateIbsCbs(
            [['cst' => '000', 'classification' => '000001', 'amount' => 1]],
            '2027-06-01',
        );
    }

    /**
     * @return array<string, array{0: list<array<string, mixed>>, 1: string, 2: string, 3: string}>
     */
    public static function refusals(): array
    {
        $ok = ['cst' => '000', 'classification' => '000001', 'amount' => 1];

        return [
            'no items' => [[], '2026-06-01', 'nfe', 'items'],
            'too many items' => [array_fill(0, 991, $ok), '2026-06-01', 'nfe', 'items'],
            'short cst' => [[['cst' => '00'] + $ok], '2026-06-01', 'nfe', 'cst'],
            'short classification' => [
                [['classification' => '0001'] + $ok],
                '2026-06-01',
                'nfe',
                'classification',
            ],
            'neither amount nor base' => [
                [['cst' => '000', 'classification' => '000001']],
                '2026-06-01',
                'nfe',
                'amount',
            ],
            'zero amount' => [[['amount' => 0] + $ok], '2026-06-01', 'nfe', 'amount'],
            'rate over 100' => [[['rate_city' => 100.1] + $ok], '2026-06-01', 'nfe', 'rate_city'],
            'negative reduction' => [
                [['reduction_cbs' => -1] + $ok],
                '2026-06-01',
                'nfe',
                'reduction_cbs',
            ],
            'unknown key' => [[['ncm' => '1'] + $ok], '2026-06-01', 'nfe', 'ncm'],
            'bad date' => [[$ok], '2026-13-01', 'nfe', 'operationDate'],
            'date without padding' => [[$ok], '2026-6-1', 'nfe', 'operationDate'],
            'bad document type' => [[$ok], '2026-06-01', 'cte', 'documentType'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    #[DataProvider('refusals')]
    public function testRefusedBeforeTheCall(
        array $items,
        string $date,
        string $type,
        string $named,
    ): void {
        $client = $this->client(self::answer());

        try {
            $client->calculateIbsCbs($items, $date, $type);
            $this->fail('expected an InvoiceError');
        } catch (InvoiceError $error) {
            $this->assertStringContainsString($named, $error->getMessage());
        }
        $this->assertCount(0, $this->sent);
    }

    public function testRatesOutOfRangeAreRefused(): void
    {
        $this->expectException(InvoiceError::class);

        new IbsCbsRates(rateFederal: 101.0);
    }

    public function testUnknownRatesKeysAreRefused(): void
    {
        $client = $this->client(self::answer());

        $this->expectException(InvoiceError::class);

        $client->calculateIbsCbs(
            [['cst' => '000', 'classification' => '000001', 'amount' => 1]],
            '2026-06-01',
            rates: ['rate' => 1],
        );
    }
}
