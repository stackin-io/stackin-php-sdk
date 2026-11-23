<?php

declare(strict_types=1);

namespace Stackin\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Address;
use Stackin\Br\Intermediary;
use Stackin\Br\Payment;
use Stackin\Br\PaymentCard;
use Stackin\Br\Product;
use Stackin\Br\Transport;
use Stackin\Br\TransportVolume;
use Stackin\Client;
use Stackin\DocumentType;
use Stackin\Errors\InvoiceError;
use Stackin\Invoice;
use Stackin\Items;

final class SaleTermsTest extends TestCase
{
    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $sent = [];

    /**
     * @template T of Client
     * @param T $client
     * @param array<string, mixed> $body
     * @return T
     */
    private function wire(Client $client, array $body = ['result' => []], int $status = 200): Client
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, [], (string) json_encode($body)),
        ]));
        $stack->push(Middleware::history($this->sent));
        $property = new ReflectionProperty(Client::class, 'http');
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $body = json_decode((string) $this->sent[0]['request']->getBody(), true);
        $this->assertIsArray($body);

        return $body;
    }

    private static function nfeItem(): Product
    {
        return new Product(description: 'Widget', amount: 10.0, ncm: '84713012', cfop: '5102');
    }

    private function address(): Address
    {
        return new Address(
            state: 'SC',
            street: 'Rua das Flores',
            number: '1200',
            neighborhood: 'Centro',
            city: 'Joinville',
            zipCode: '89201100',
        );
    }

    /**
     * @param Payment[]|null $payments
     * @return array<string, mixed>
     */
    private function issueNfe(
        ?string $presence = null,
        ?Intermediary $intermediary = null,
        ?Transport $transport = null,
        ?array $payments = null,
        float|string|null $change = null,
        ?Product $item = null,
    ): array {
        $invoice = $this->wire(new Invoice(apiKey: 'key', baseUrl: 'https://sdk.test'));
        $this->assertInstanceOf(Invoice::class, $invoice);
        $invoice->issue(
            DocumentType::NFE,
            'Maria',
            '12345678909',
            [$item ?? self::nfeItem()],
            $this->address(),
            presence: $presence,
            intermediary: $intermediary,
            transport: $transport,
            payments: $payments,
            change: $change,
        );

        return $this->body();
    }

    public function testNothingIsSentWhenUnset(): void
    {
        $body = $this->issueNfe();

        foreach (['presence', 'intermediary', 'transport', 'payments', 'change'] as $key) {
            $this->assertArrayNotHasKey($key, $body);
        }
        $this->assertArrayNotHasKey('net_weight', $body['items'][0]['product']);
        $this->assertArrayNotHasKey('gross_weight', $body['items'][0]['product']);
    }

    public function testSaleTermsReachTheBodyUnderTheirWireNames(): void
    {
        $body = $this->issueNfe(
            presence: '2',
            intermediary: new Intermediary('12.345.678/0001-95', 'loja-da-maria'),
            transport: new Transport('0', [
                new TransportVolume(2, 'CAIXA', 'Acme', '1-2', 1.5, '1.750'),
            ]),
            payments: [
                new Payment('03', 10.0, card: new PaymentCard('1', '98765432000198', '02', 'A1B2')),
                new Payment('99', '0.30', 'Vale troca'),
            ],
            change: 0.3,
        );

        $this->assertSame('2', $body['presence']);
        $this->assertSame(
            ['tax_id' => '12.345.678/0001-95', 'identifier' => 'loja-da-maria'],
            $body['intermediary'],
        );
        $this->assertSame([
            'freight_mode' => '0',
            'volumes' => [[
                'quantity' => 2,
                'species' => 'CAIXA',
                'brand' => 'Acme',
                'numbering' => '1-2',
                'net_weight' => '1.5',
                'gross_weight' => '1.750',
            ]],
        ], $body['transport']);
        $this->assertSame([
            [
                'method' => '03',
                'amount' => '10',
                'card' => [
                    'integration' => '1',
                    'tax_id' => '98765432000198',
                    'brand' => '02',
                    'authorization' => 'A1B2',
                ],
            ],
            ['method' => '99', 'amount' => '0.30', 'description' => 'Vale troca'],
        ], $body['payments']);
        $this->assertSame('0.3', $body['change']);
    }

    public function testPresenceZeroIsNotDropped(): void
    {
        $this->assertSame('0', $this->issueNfe(presence: '0')['presence']);
    }

    public function testChangeZeroIsNotDropped(): void
    {
        $this->assertSame('0', $this->issueNfe(change: 0.0)['change']);
    }

    public function testEmptyVolumesGoOutAsAnEmptyListAndNullLeavesThemOut(): void
    {
        $empty = $this->issueNfe(transport: new Transport('9', []));
        $this->assertSame([], $empty['transport']['volumes']);

        $none = $this->issueNfe(transport: new Transport('9'));
        $this->assertSame(['freight_mode' => '9'], $none['transport']);
    }

    public function testProductWeightsGoUnderProduct(): void
    {
        $body = $this->issueNfe(item: new Product(
            description: 'Widget',
            amount: 10.0,
            ncm: '84713012',
            cfop: '5102',
            netWeight: 0.25,
            grossWeight: '0.300',
        ));

        $this->assertSame('0.25', $body['items'][0]['product']['net_weight']);
        $this->assertSame('0.300', $body['items'][0]['product']['gross_weight']);
    }

    public function testValidateCarriesTheSaleTerms(): void
    {
        $invoice = $this->wire(
            new Invoice(apiKey: 'key', baseUrl: 'https://sdk.test'),
            ['valid' => true, 'warnings' => [], 'errors' => []],
        );
        $this->assertInstanceOf(Invoice::class, $invoice);
        $invoice->validate(
            DocumentType::NFE,
            'Maria',
            '12345678909',
            [self::nfeItem()],
            $this->address(),
            presence: '1',
            payments: [new Payment('01', 10.0)],
        );

        $path = $this->sent[0]['request']->getUri()->getPath();
        $this->assertSame('/api/v1/invoices/validate', $path);
        $this->assertSame('1', $this->body()['presence']);
        $this->assertSame([['method' => '01', 'amount' => '10']], $this->body()['payments']);
    }

    public function testInvalidPresenceIsRefusedButFourIsNot(): void
    {
        $this->assertSame('4', $this->issueNfe(presence: '4')['presence']);

        $this->expectException(InvoiceError::class);
        $this->issueNfe(presence: '6');
    }

    public function testFreightModeMustBeInItsSet(): void
    {
        $this->expectException(InvoiceError::class);
        new Transport('5');
    }

    public function testMethodNinetyNineNeedsADescription(): void
    {
        $this->expectException(InvoiceError::class);
        new Payment('99', 5.0);
    }

    public function testMethodMustBeTwoDigits(): void
    {
        $this->expectException(InvoiceError::class);
        new Payment('3', 5.0);
    }

    public function testVolumeWeightsTakeThreePlaces(): void
    {
        $this->expectException(InvoiceError::class);
        new TransportVolume(netWeight: '1.2345');
    }

    public function testVolumeGrossWeightTakesThreePlaces(): void
    {
        $this->expectException(InvoiceError::class);
        new TransportVolume(grossWeight: 1.2345);
    }

    public function testLineAndCatalogWeightsHaveNoPlacesLimit(): void
    {
        $product = new Product(description: 'Widget', netWeight: '1.2345678');
        $this->assertSame('1.2345678', $product->toArray()['product']['net_weight']);
    }

    public function testNegativeWeightIsRefused(): void
    {
        $this->expectException(InvoiceError::class);
        new TransportVolume(grossWeight: -1.0);
    }

    public function testPaymentAmountTakesTwoPlaces(): void
    {
        $this->expectException(InvoiceError::class);
        new Payment('01', '10.005');
    }

    public function testIdentifierLengthIsChecked(): void
    {
        $this->expectException(InvoiceError::class);
        new Intermediary('12345678000195', 'x');
    }

    public function testCardIntegrationMustBeOneOrTwo(): void
    {
        $this->expectException(InvoiceError::class);
        new PaymentCard('3');
    }

    public function testPaymentsAreLimitedToOneToAHundred(): void
    {
        $this->expectException(InvoiceError::class);
        $this->issueNfe(payments: []);
    }

    public function testMoreThanAHundredPaymentsAreRefused(): void
    {
        $this->expectException(InvoiceError::class);
        $this->issueNfe(payments: array_fill(0, 101, new Payment('01', 1.0)));
    }

    public function testNegativeProductWeightIsRefused(): void
    {
        $this->expectException(InvoiceError::class);
        new Product(description: 'Widget', netWeight: '-1');
    }

    public function testCrossFieldFiscalRulesAreLeftToTheApi(): void
    {
        $body = $this->issueNfe(
            presence: '5',
            intermediary: new Intermediary('11111111111111', 'abc'),
            payments: [new Payment('90', 0.0, card: new PaymentCard('2'))],
            change: 10.0,
        );

        $this->assertSame('5', $body['presence']);
        $this->assertSame('10', $body['change']);
    }

    public function testCatalogItemWeightsAndIbsCbs(): void
    {
        $items = $this->wire(
            new Items(apiKey: 'key', baseUrl: 'https://sdk.test'),
            ['result' => ['id' => 'x']],
            201,
        );
        $this->assertInstanceOf(Items::class, $items);
        $items->upsert(
            'product',
            'Widget',
            netWeight: 0.25,
            grossWeight: '0.300000',
            ibsCbsCst: '000',
            ibsCbsClass: '000001',
        );

        $body = $this->body();
        $this->assertSame('0.25', $body['net_weight']);
        $this->assertSame('0.300000', $body['gross_weight']);
        $this->assertSame('000', $body['ibs_cbs_cst']);
        $this->assertSame('000001', $body['ibs_cbs_class']);
    }

    public function testCatalogItemOmitsThemWhenUnset(): void
    {
        $items = $this->wire(
            new Items(apiKey: 'key', baseUrl: 'https://sdk.test'),
            ['result' => ['id' => 'x']],
            201,
        );
        $this->assertInstanceOf(Items::class, $items);
        $items->upsert('product', 'Widget');

        $body = $this->body();
        foreach (['net_weight', 'gross_weight', 'ibs_cbs_cst', 'ibs_cbs_class'] as $key) {
            $this->assertArrayNotHasKey($key, $body);
        }
    }

    public function testNegativeCatalogWeightIsRefused(): void
    {
        $items = new Items(apiKey: 'key', baseUrl: 'https://sdk.test');
        $this->expectException(InvoiceError::class);
        $items->upsert('product', 'Widget', netWeight: -0.5);
    }

    public function testConsultReturnsTheStoredTermsAsTheDetailNamesThem(): void
    {
        $detail = [
            'id' => 'inv-1',
            'document_type' => 'nfe',
            'status' => 'authorized',
            'presence' => '2',
            'intermediary' => ['tax_id' => '12345678000195', 'identifier' => 'loja'],
            'transport' => [
                'freight_mode' => '1',
                'volumes' => [
                    ['quantity' => 1, 'net_weight' => '1.500', 'gross_weight' => '1.750'],
                ],
            ],
            'payments' => [
                ['method' => '03', 'amount' => '10.00', 'card' => ['integration' => '1']],
            ],
            'change' => '0.00',
        ];
        $invoice = $this->wire(
            new Invoice(apiKey: 'key', baseUrl: 'https://sdk.test'),
            ['result' => $detail],
        );
        $this->assertInstanceOf(Invoice::class, $invoice);

        $key = '35240112345678000190550010000001231234567890';
        $result = $invoice->consult($key, DocumentType::NFE);

        $this->assertSame('2', $result['presence']);
        $this->assertSame('loja', $result['intermediary']['identifier']);
        $this->assertSame('1', $result['transport']['freight_mode']);
        $this->assertSame('1.500', $result['transport']['volumes'][0]['net_weight']);
        $this->assertSame('10.00', $result['payments'][0]['amount']);
        $this->assertSame('0.00', $result['change']);
    }
}
