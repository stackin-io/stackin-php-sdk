<?php

declare(strict_types=1);

namespace Stackin\Tests;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Address;
use Stackin\Br\Fuel;
use Stackin\Br\Product;
use Stackin\Direction;
use Stackin\DocumentType;
use Stackin\Errors\InvoiceError;
use Stackin\Export;
use Stackin\Invoice;
use Stackin\Purpose;

/**
 * The issue() fields added with API v0.8–v0.9: each one reaches the body
 * under its API name when given, and none of them appears when not.
 */
final class IssueFieldsTest extends TestCase
{
    private const REFERENCED_KEY = '35240112345678000190550010000001231234567890';

    private MockHandler $mock;

    private function invoice(): Invoice
    {
        $this->mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'result' => ['access_key' => 'abc123'],
            ])),
        ]);
        $invoice = new Invoice(apiKey: 'key', baseUrl: 'https://example.com');
        $property = new ReflectionProperty(Invoice::class, 'http');
        $property->setValue($invoice, new HttpClient([
            'handler' => HandlerStack::create($this->mock),
            'http_errors' => false,
        ]));

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function sentBody(): array
    {
        $request = $this->mock->getLastRequest();
        $this->assertNotNull($request);
        $body = json_decode((string) $request->getBody(), true);
        $this->assertIsArray($body);

        return $body;
    }

    private function nfeItem(): Product
    {
        return new Product(description: 'Widget', amount: 10.0, ncm: '84713012', cfop: '5102');
    }

    private function brazilAddress(): Address
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

    private function abroadAddress(): Address
    {
        return new Address(
            state: 'EX',
            street: 'Avenida Corrientes',
            number: '1500',
            neighborhood: 'San Nicolas',
            city: 'Buenos Aires',
            countryCode: '0639',
            countryName: 'Argentina',
        );
    }

    public function testAnExistingCallSendsTheSameBody(): void
    {
        $this->invoice()->issue(
            DocumentType::NFE,
            'Acme',
            '12345678909',
            [$this->nfeItem()],
            $this->brazilAddress(),
        );

        $this->assertSame(
            ['document_type', 'client_name', 'tax_id', 'items', 'recipient_address'],
            array_keys($this->sentBody()),
        );
    }

    public function testSendsTheDocumentDiscountAsADecimalString(): void
    {
        $this->invoice()->issue(
            DocumentType::NFSE,
            'Acme',
            '12345678909',
            [new Product(description: 'Servico', amount: 100.0)],
            discount: 12.5,
        );

        $this->assertSame('12.5', $this->sentBody()['discount']);
    }

    public function testSendsIssuedAtAsRfc3339WithItsOffset(): void
    {
        $issuedAt = new DateTimeImmutable(
            '2026-11-14 09:30:00',
            new DateTimeZone('America/Sao_Paulo'),
        );

        $this->invoice()->issue(
            DocumentType::NFSE,
            'Acme',
            '12345678909',
            [new Product(description: 'Servico', amount: 100.0)],
            issuedAt: $issuedAt,
        );

        $this->assertSame('2026-11-14T09:30:00-03:00', $this->sentBody()['issued_at']);
    }

    public function testSendsPurposeDirectionAndReferencedKeys(): void
    {
        $this->invoice()->issue(
            DocumentType::NFE,
            'Acme',
            '12345678909',
            [$this->nfeItem()],
            $this->brazilAddress(),
            purpose: Purpose::RETURN,
            direction: Direction::IN,
            referencedKeys: [self::REFERENCED_KEY],
        );

        $body = $this->sentBody();
        $this->assertSame('return', $body['purpose']);
        $this->assertSame('in', $body['direction']);
        $this->assertSame([self::REFERENCED_KEY], $body['referenced_keys']);
    }

    public function testSendsReferencedKeysAsAList(): void
    {
        $this->invoice()->issue(
            DocumentType::NFE,
            'Acme',
            '12345678909',
            [$this->nfeItem()],
            $this->brazilAddress(),
            purpose: Purpose::COMPLEMENTARY,
            referencedKeys: [3 => self::REFERENCED_KEY],
        );

        $this->assertSame([self::REFERENCED_KEY], $this->sentBody()['referenced_keys']);
    }

    public function testPurposeAndDirectionCarryTheApiValues(): void
    {
        $this->assertSame(
            ['normal', 'complementary', 'adjustment', 'return'],
            array_map(static fn (Purpose $case): string => $case->value, Purpose::cases()),
        );
        $this->assertSame(
            ['out', 'in'],
            array_map(static fn (Direction $case): string => $case->value, Direction::cases()),
        );
    }

    public function testAnNfeAbroadSendsForeignIdAndExportWithoutTaxId(): void
    {
        $this->invoice()->issue(
            DocumentType::NFE,
            'Importadora Sur',
            null,
            [new Product(description: 'Widget', amount: 10.0, ncm: '84713012', cfop: '7102')],
            $this->abroadAddress(),
            foreignId: 'AR1234567',
            export: new Export(exitState: 'RS', place: 'Uruguaiana', dispatchPlace: 'Porto Seco'),
        );

        $body = $this->sentBody();
        $this->assertArrayNotHasKey('tax_id', $body);
        $this->assertSame('AR1234567', $body['foreign_id']);
        $this->assertSame(
            ['exit_state' => 'RS', 'place' => 'Uruguaiana', 'dispatch_place' => 'Porto Seco'],
            $body['export'],
        );
        $this->assertSame(
            [
                'state' => 'EX',
                'street' => 'Avenida Corrientes',
                'number' => '1500',
                'neighborhood' => 'San Nicolas',
                'city' => 'Buenos Aires',
                'country_code' => '0639',
                'country_name' => 'Argentina',
            ],
            $body['recipient_address'],
        );
    }

    public function testExportLeavesOutAnUnsetDispatchPlace(): void
    {
        $export = new Export(exitState: 'SP', place: 'Porto de Santos');

        $this->assertSame(['exit_state' => 'SP', 'place' => 'Porto de Santos'], $export->toArray());
    }

    public function testAnNfeInBrazilStillRequiresTaxId(): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('taxId is required');

        $this->invoice()->issue(
            DocumentType::NFE,
            'Acme',
            null,
            [$this->nfeItem()],
            $this->brazilAddress(),
        );
    }

    public function testAnNfseRequiresTaxIdEvenWithAnAddressAbroad(): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('taxId is required');

        $this->invoice()->issue(
            DocumentType::NFSE,
            'Acme',
            null,
            [new Product(description: 'Servico', amount: 100.0)],
            $this->abroadAddress(),
        );
    }

    public function testAnNfeAbroadNeedsNoZipCode(): void
    {
        $this->invoice()->issue(
            DocumentType::NFE,
            'Importadora Sur',
            null,
            [$this->nfeItem()],
            $this->abroadAddress(),
        );

        $this->assertArrayNotHasKey('zip_code', $this->sentBody()['recipient_address']);
    }

    public function testAnNfeInBrazilStillRequiresZipCode(): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage(
            'recipientAddress is missing required fields for NFE: zipCode',
        );

        $this->invoice()->issue(
            DocumentType::NFE,
            'Acme',
            '12345678909',
            [$this->nfeItem()],
            new Address(
                state: 'SC',
                street: 'Rua das Flores',
                number: '1200',
                neighborhood: 'Centro',
                city: 'Joinville',
            ),
        );
    }

    public function testTheLatin1CheckLetsTheNewFieldsThrough(): void
    {
        $this->invoice()->issue(
            DocumentType::NFE,
            'Importadora Sur',
            null,
            [
                new Product(
                    description: 'Gasolina',
                    amount: 10.0,
                    ncm: '27101259',
                    cfop: '7102',
                    fuel: new Fuel(
                        anpCode: '320102001',
                        anpDescription: 'GASOLINA C COMUM',
                        consumerState: 'SC',
                        biodieselPercent: 0.0,
                    ),
                ),
            ],
            $this->abroadAddress(),
            discount: 1.0,
            issuedAt: new DateTimeImmutable('2026-11-14T09:30:00-03:00'),
            purpose: Purpose::NORMAL,
            direction: Direction::OUT,
            referencedKeys: [self::REFERENCED_KEY],
            foreignId: 'AR1234567',
            export: new Export(exitState: 'RS', place: 'Uruguaiana'),
        );

        $fuel = $this->sentBody()['items'][0]['product']['br']['fuel'];
        $this->assertSame('320102001', $fuel['anp_code']);
    }

    public function testTheLatin1CheckReadsTheCountryName(): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('recipient_address.country_name');

        $this->invoice()->issue(
            DocumentType::NFE,
            'Acme',
            null,
            [$this->nfeItem()],
            new Address(
                state: 'EX',
                street: 'Chuo-dori',
                number: '1',
                neighborhood: 'Ginza',
                city: 'Tokyo',
                countryCode: '3999',
                countryName: '日本',
            ),
        );
    }

    public function testTheLatin1CheckReadsTheFuelDescription(): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('items[0].fuel.anp_description');

        $this->invoice()->issue(
            DocumentType::NFE,
            'Acme',
            '12345678909',
            [
                new Product(
                    description: 'Gasolina',
                    amount: 10.0,
                    ncm: '27101259',
                    cfop: '5656',
                    fuel: new Fuel(
                        anpCode: '320102001',
                        anpDescription: 'GASOLINA ⛽',
                        consumerState: 'SC',
                    ),
                ),
            ],
            $this->brazilAddress(),
        );
    }
}
