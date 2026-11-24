<?php

declare(strict_types=1);

namespace Stackin\Tests;

use Closure;
use DateTimeImmutable;
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
use Stackin\Br\ApproximateTaxes;
use Stackin\Br\Construction;
use Stackin\Br\DeductionDocument;
use Stackin\Br\DeductionSupplier;
use Stackin\Br\Deductions;
use Stackin\Br\Event;
use Stackin\Br\FederalTaxes;
use Stackin\Br\ForeignTrade;
use Stackin\Br\IbsCbs;
use Stackin\Br\IbsCbsDeferral;
use Stackin\Br\IbsCbsGovernment;
use Stackin\Br\IbsCbsRecipient;
use Stackin\Br\IbsCbsRecipientAddress;
use Stackin\Br\IbsCbsRegularTaxation;
use Stackin\Br\IssDetails;
use Stackin\Br\IssMunicipalBenefit;
use Stackin\Br\IssSuspension;
use Stackin\Br\MovableGood;
use Stackin\Br\NfseDetails;
use Stackin\Br\NfseIntermediary;
use Stackin\Br\NfseProvider;
use Stackin\Br\OtherDfe;
use Stackin\Br\OtherDocument;
use Stackin\Br\PersonAddress;
use Stackin\Br\PisCofins;
use Stackin\Br\Product;
use Stackin\Br\RealEstate;
use Stackin\Br\RealEstateBaseAdjustment;
use Stackin\Br\RealEstateLease;
use Stackin\Br\RealEstateUnit;
use Stackin\Br\References;
use Stackin\Br\ServiceAddress;
use Stackin\Br\Substitution;
use Stackin\Client;
use Stackin\Company;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\InvoiceError;
use Stackin\FiscalReference;
use Stackin\Invoice;

final class NfseFieldsTest extends TestCase
{
    private const KEY = '11111111111111111111111111111111111111111111111111';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    private function inject(Client $client, Response $response): void
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->sent));

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private static function json(array $body): Response
    {
        return new Response(200, [], (string) json_encode($body));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $this->assertCount(1, $this->sent);

        return json_decode((string) $this->sent[0]['request']->getBody(), true);
    }

    private function service(?Closure $mutate = null): Product
    {
        $line = ['description' => 'Consultoria', 'amount' => 100.0, 'serviceCode' => '1.07'];
        if ($mutate !== null) {
            $line = $mutate($line);
        }

        return new Product(...$line);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function issue(array $extra = [], ?Product $product = null): array
    {
        $invoice = new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($invoice, self::json(['result' => []]));
        $invoice->issue(...array_merge([
            'documentType' => DocumentType::NFSE,
            'clientName' => 'Maria',
            'taxId' => '12345678909',
            'items' => [$product ?? $this->service()],
        ], $extra));

        return $this->body();
    }

    public function testNfseGroupsGoOutAsSnakeCaseWithDecimalStrings(): void
    {
        $sent = $this->issue(['nfse' => new NfseDetails(
            approximateTaxes: new ApproximateTaxes(federalPercent: 13.45, statePercent: 0.0),
            competenceDate: new DateTimeImmutable('2026-11-20'),
            serviceCityCode: '3550308',
            iss: new IssDetails(
                taxation: 'taxable',
                rate: 2.5,
                retention: 'tomador',
                suspension: new IssSuspension('judicial', str_repeat('7', 30)),
                municipalBenefit: new IssMunicipalBenefit(
                    str_repeat('3', 14),
                    reductionPercent: 10,
                ),
            ),
            federal: new FederalTaxes(
                pisCofins: new PisCofins('01', base: 100.0, pisRate: 0.65, withholdingType: 0),
                socialSecurityWithheld: 1.5,
            ),
            deductions: new Deductions(documents: [new DeductionDocument(
                type: 2,
                issueDate: '2026-11-01',
                totalAmount: 50.0,
                deductedAmount: 20.5,
                nfseKey: self::KEY,
            )]),
            substitution: new Substitution(self::KEY, '5'),
            construction: new Construction(workIdType: 'cib', workId: '12345678'),
            event: new Event('Show', '2026-11-02', '2026-11-03', eventId: 'EV1'),
            references: new References(orderNumber: 'PO-1', orderItems: ['a', 'b']),
            provider: new NfseProvider('12345678901234', '+55 (11) 99999-0000', 'dps@example.com'),
            recipientCaepf: '43210987654321',
            intermediary: new NfseIntermediary(
                name: 'Broker',
                foreignId: 'NIF1',
                address: new PersonAddress(
                    street: 'Rua',
                    number: '1',
                    neighborhood: 'Centro',
                    countryCode: 'PT',
                    postalCode: '1000',
                    city: 'Lisboa',
                    state: 'Lisboa',
                ),
            ),
            foreignTrade: new ForeignTrade(
                serviceMode: 1,
                relationship: 0,
                currencyCode: '220',
                foreignAmount: 10.5,
                providerSupport: 1,
                recipientSupport: 1,
                temporaryGoods: 2,
                importDeclaration: 'DI1',
                shareWithMdic: true,
            ),
        )]);

        $this->assertEquals([
            'approximate_taxes' => ['federal_percent' => '13.45', 'state_percent' => '0'],
            'competence_date' => '2026-11-20',
            'service_city_code' => '3550308',
            'iss' => [
                'taxation' => 'taxable',
                'rate' => '2.5',
                'retention' => 'tomador',
                'suspension' => ['type' => 'judicial', 'process_number' => str_repeat('7', 30)],
                'municipal_benefit' => ['id' => str_repeat('3', 14), 'reduction_percent' => '10'],
            ],
            'federal' => [
                'pis_cofins' => [
                    'cst' => '01',
                    'base' => '100',
                    'pis_rate' => '0.65',
                    'withholding_type' => 0,
                ],
                'social_security_withheld' => '1.5',
            ],
            'deductions' => ['documents' => [[
                'type' => 2,
                'issue_date' => '2026-11-01',
                'total_amount' => '50',
                'deducted_amount' => '20.5',
                'nfse_key' => self::KEY,
            ]]],
            'substitution' => ['access_key' => self::KEY, 'reason_code' => '05'],
            'construction' => ['work_id_type' => 'cib', 'work_id' => '12345678'],
            'event' => [
                'name' => 'Show',
                'start_date' => '2026-11-02',
                'end_date' => '2026-11-03',
                'event_id' => 'EV1',
            ],
            'references' => ['order_number' => 'PO-1', 'order_items' => ['a', 'b']],
            'intermediary' => [
                'name' => 'Broker',
                'foreign_id' => 'NIF1',
                'address' => [
                    'street' => 'Rua',
                    'number' => '1',
                    'neighborhood' => 'Centro',
                    'country_code' => 'PT',
                    'postal_code' => '1000',
                    'city' => 'Lisboa',
                    'state' => 'Lisboa',
                ],
            ],
            'provider' => [
                'caepf' => '12345678901234',
                'phone' => '+5511999990000',
                'email' => 'dps@example.com',
            ],
            'recipient_caepf' => '43210987654321',
            'foreign_trade' => [
                'service_mode' => 1,
                'relationship' => 0,
                'currency_code' => '220',
                'foreign_amount' => '10.5',
                'provider_support' => 1,
                'recipient_support' => 1,
                'temporary_goods' => 2,
                'import_declaration' => 'DI1',
                'share_with_mdic' => true,
            ],
        ], $sent['nfse']);
    }

    public function testDeductionOtherDfeGoesOut(): void
    {
        $sent = $this->issue(['nfse' => new NfseDetails(deductions: new Deductions(documents: [
            new DeductionDocument(
                type: 99,
                issueDate: '2026-11-01',
                totalAmount: 50.0,
                deductedAmount: 20.0,
                description: 'outros',
                otherDfe: new OtherDfe('ABC123', 'Fatura de energia'),
            ),
        ]))]);

        $this->assertSame(
            ['key' => 'ABC123', 'description' => 'Fatura de energia'],
            $sent['nfse']['deductions']['documents'][0]['other_dfe'],
        );
    }

    public function testNothingNewIsSentWhenNothingIsSet(): void
    {
        foreach ([[], ['nfse' => new NfseDetails()]] as $extra) {
            $sent = $this->issue($extra);
            $keys = ['nfse', 'recipient_municipal_registration', 'foreign_id_missing_reason'];
            foreach ($keys as $key) {
                $this->assertArrayNotHasKey($key, $sent);
            }
        }
    }

    public function testRecipientFieldsGoOut(): void
    {
        $sent = $this->issue([
            'recipientMunicipalRegistration' => '12.345-6',
            'foreignIdMissingReason' => 2,
            'recipientAddress' => new Address(
                street: 'Fifth Avenue',
                number: '350',
                neighborhood: 'Midtown',
                city: 'New York',
                state: 'EX',
                complement: 'Suite 5',
                countryIso2: 'US',
                postalCode: '10118',
                province: 'NY',
            ),
        ]);

        $this->assertSame('123456', $sent['recipient_municipal_registration']);
        $this->assertSame(2, $sent['foreign_id_missing_reason']);
        $this->assertSame('Suite 5', $sent['recipient_address']['complement']);
        $this->assertSame('US', $sent['recipient_address']['country_iso2']);
        $this->assertSame('10118', $sent['recipient_address']['postal_code']);
        $this->assertSame('NY', $sent['recipient_address']['province']);
    }

    public function testRecipientRefusals(): void
    {
        $cases = [
            'recipientMunicipalRegistration must be' => fn () => $this->issue(
                ['recipientMunicipalRegistration' => str_repeat('1', 16)],
            ),
            'foreignIdMissingReason takes 1' => fn () => $this->issue(
                ['foreignIdMissingReason' => 0],
            ),
            'countryIso2 must be' => fn () => new Address(countryIso2: 'usa'),
            'postalCode must be' => fn () => new Address(postalCode: str_repeat('1', 12)),
            'province must be' => fn () => new Address(province: str_repeat('x', 61)),
        ];
        foreach ($cases as $message => $call) {
            try {
                $call();
                $this->fail("expected a refusal: {$message}");
            } catch (InvoiceError $error) {
                $this->assertStringContainsString($message, $error->getMessage());
            }
        }
    }

    public function testServiceLineFieldsGoOut(): void
    {
        $sent = $this->issue([], $this->service(static fn (array $line): array => $line + [
            'nbs' => '115021000',
            'municipalServiceCode' => '001',
            'internalCode' => 'SRV01',
            'conditionalDiscount' => 5.5,
        ]));

        $item = $sent['items'][0];
        $this->assertSame('115021000', $item['nbs']);
        $this->assertSame('001', $item['municipal_service_code']);
        $this->assertSame('SRV01', $item['internal_code']);
        $this->assertSame('5.5', $item['conditional_discount']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function badLines(): array
    {
        return [
            'municipal code' => [['municipalServiceCode' => '12'], 'municipalServiceCode'],
            'internal code' => [['internalCode' => 'ab-1'], 'internalCode'],
            'discount zero' => [
                ['conditionalDiscount' => 0.0],
                'conditionalDiscount must be above 0',
            ],
            'discount places' => [
                ['conditionalDiscount' => '1.234'],
                'conditionalDiscount takes at most 2',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[DataProvider('badLines')]
    public function testServiceLineRefusals(array $extra, string $message): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage($message);
        $this->service(static fn (array $line): array => $line + $extra);
    }

    public function testIbsCbsNfseGroupsGoOut(): void
    {
        $group = new IbsCbs(
            cst: '000',
            classification: '000001',
            operationIndicator: '100301',
            personalUse: false,
            government: new IbsCbsGovernment('2', '4'),
            referencedNfse: [self::KEY],
            recipient: new IbsCbsRecipient(
                name: 'Dest',
                nif: 'N1',
                address: new IbsCbsRecipientAddress(
                    street: 'R',
                    number: '1',
                    neighborhood: 'C',
                    countryCode: 'PT',
                    postalCode: '1',
                    city: 'L',
                    region: 'L',
                ),
            ),
            realEstate: new RealEstate(
                cityCode: '3550308',
                lease: new RealEstateLease(100, 1500, originalDueDate: '2026-12-05'),
                units: [new RealEstateUnit(
                    cib: 'ABCD1234',
                    baseAdjustments: [new RealEstateBaseAdjustment('99', 10, 'x')],
                )],
            ),
            movableGoods: [new MovableGood('84713012', 'Notebook', 2)],
            regularTaxation: new IbsCbsRegularTaxation('000', '000001'),
            deferral: new IbsCbsDeferral(0, 10, 50),
            presumedCredit: '01',
        );
        $sent = $this->issue([], $this->service(static fn (array $line): array => $line + [
            'nbs' => '115021000',
            'ibsCbs' => $group,
        ]));

        $this->assertEquals([
            'cst' => '000',
            'classification' => '000001',
            'operation_indicator' => '100301',
            'personal_use' => false,
            'government' => ['operation_type' => '2', 'entity_type' => '4'],
            'referenced_nfse' => [self::KEY],
            'recipient' => [
                'name' => 'Dest',
                'nif' => 'N1',
                'address' => [
                    'street' => 'R',
                    'number' => '1',
                    'neighborhood' => 'C',
                    'country_code' => 'PT',
                    'postal_code' => '1',
                    'city' => 'L',
                    'region' => 'L',
                ],
            ],
            'real_estate' => [
                'city_code' => '3550308',
                'lease' => [
                    'co_ownership_percent' => 100,
                    'total_amount' => 1500,
                    'original_due_date' => '2026-12-05',
                ],
                'units' => [[
                    'cib' => 'ABCD1234',
                    'base_adjustments' => [['type' => '99', 'amount' => 10, 'description' => 'x']],
                ]],
            ],
            'movable_goods' => [
                ['ncm' => '84713012', 'description' => 'Notebook', 'quantity' => 2],
            ],
            'regular_taxation' => ['cst' => '000', 'classification' => '000001'],
            'deferral' => ['state_percent' => 0, 'city_percent' => 10, 'cbs_percent' => 50],
            'presumed_credit' => '01',
        ], $sent['items'][0]['product']['br']['ibs_cbs']);
    }

    /**
     * @return array<string, array{Closure, string}>
     */
    public static function refusals(): array
    {
        $supplierAbroad = new PersonAddress(
            street: 'R',
            number: '1',
            neighborhood: 'C',
            countryCode: 'PT',
            postalCode: '1',
            city: 'L',
            state: 'L',
        );
        $national = new PersonAddress(
            street: 'R',
            number: '1',
            neighborhood: 'C',
            cityCode: '3550308',
            zipCode: '01001000',
        );
        $trade = static fn (array $over = []): ForeignTrade => new ForeignTrade(...($over + [
            'serviceMode' => 1,
            'relationship' => 1,
            'currencyCode' => '220',
            'foreignAmount' => 1.0,
            'providerSupport' => 1,
            'recipientSupport' => 1,
            'temporaryGoods' => 1,
        ]));

        return [
            'approximate empty' => [
                fn () => new ApproximateTaxes(),
                'nfse.approximate_taxes needs',
            ],
            'approximate shapes' => [
                fn () => new ApproximateTaxes(simplesPercent: 3, federalAmount: 1),
                'takes one shape',
            ],
            'simples cap' => [
                fn () => new ApproximateTaxes(simplesPercent: 100),
                'nfse.approximate_taxes.simples_percent must be',
            ],
            'percent places' => [
                fn () => new ApproximateTaxes(federalPercent: '1.234'),
                'federal_percent takes at most 2',
            ],
            'iss rate zero' => [fn () => new IssDetails(rate: 0), 'nfse.iss.rate must be above 0'],
            'iss taxation' => [
                fn () => new IssDetails(taxation: 'free'),
                'taxation must be one of',
            ],
            'immunity' => [
                fn () => new IssDetails(immunityType: 0),
                'immunity_type must be one of',
            ],
            'suspension number' => [
                fn () => new IssSuspension('judicial', '12'),
                'process_number must be 30 digits',
            ],
            'benefit both' => [
                fn () => new IssMunicipalBenefit(str_repeat('1', 14), 1, 1),
                'not both',
            ],
            'federal empty' => [fn () => new FederalTaxes(), 'nfse.federal needs'],
            'pis cofins cst' => [fn () => new PisCofins('10'), 'pis_cofins.cst must be one of'],
            'withheld zero' => [
                fn () => new FederalTaxes(irrfWithheld: 0),
                'irrf_withheld must be above 0',
            ],
            'place both' => [
                fn () => new NfseDetails(serviceCityCode: '3550308', serviceCountryCode: 'PT'),
                'not both',
            ],
            'country BR' => [fn () => new NfseDetails(serviceCountryCode: 'BR'), 'cannot be BR'],
            'competence year' => [
                fn () => new NfseDetails(competenceDate: '1999-01-01'),
                'competence_date must be a date from 2000 to 2099',
            ],
            'competence format' => [
                fn () => new NfseDetails(competenceDate: '01/02/2026'),
                'must be a date as YYYY-MM-DD',
            ],
            'construction empty' => [fn () => new Construction(), 'needs work_id'],
            'construction cib length' => [
                fn () => new Construction(workIdType: 'cib', workId: '123'),
                'exactly 8 characters',
            ],
            'construction id without type' => [
                fn () => new Construction(workId: '123'),
                'go together',
            ],
            'construction address abroad' => [
                fn () => new NfseDetails(
                    serviceCountryCode: 'PT',
                    construction: new Construction(
                        address: new ServiceAddress('01001000', 'R', '1', neighborhood: 'C'),
                    ),
                ),
                'construction.address is an address in Brazil',
            ],
            'event place' => [
                fn () => new Event('Show', '2026-01-01', '2026-01-02'),
                'event_id or address',
            ],
            'references too many' => [
                fn () => new References(orderItems: array_fill(0, 100, 'a')),
                'at most 99',
            ],
            'deductions empty' => [fn () => new Deductions(), 'needs percent, amount or documents'],
            'deductions both' => [fn () => new Deductions(percent: 1, amount: 1), 'not both'],
            'document type' => [
                fn () => new DeductionDocument(10, '2026-01-01', 1, 1, nfseKey: self::KEY),
                'type must be one of',
            ],
            'document identification' => [
                fn () => new DeductionDocument(2, '2026-01-01', 1, 1),
                'exactly one of nfse_key',
            ],
            'supplier ids' => [
                fn () => new DeductionSupplier(name: 'S', address: $supplierAbroad),
                'takes tax_id',
            ],
            'supplier tax id abroad' => [
                fn () => new DeductionSupplier(
                    name: 'S',
                    address: $supplierAbroad,
                    taxId: '12345678909',
                ),
                'supplier.address must be in Brazil',
            ],
            'document other' => [
                fn () => new DeductionDocument(
                    2,
                    '2026-01-01',
                    1,
                    1,
                    otherDocument: new OtherDocument(''),
                    nfseKey: self::KEY,
                ),
                'other_document.number is required',
            ],
            'provider caepf' => [
                fn () => new NfseProvider(caepf: '123'),
                'caepf must be 14 digits',
            ],
            'provider phone' => [
                fn () => new NfseProvider(phone: '12'),
                'phone must be 8 to 15 digits',
            ],
            'provider email' => [
                fn () => new NfseProvider(email: 'nope'),
                'email must be an e-mail address',
            ],
            'recipient caepf' => [
                fn () => new NfseDetails(recipientCaepf: '1234'),
                'recipient_caepf must be 14 digits',
            ],
            'other dfe without description' => [
                fn () => new OtherDfe('K', ''),
                'other_dfe.description is required',
            ],
            'other dfe and nfse key' => [
                fn () => new DeductionDocument(
                    2,
                    '2026-01-01',
                    1,
                    1,
                    nfseKey: self::KEY,
                    otherDfe: new OtherDfe('K', 'D'),
                ),
                'exactly one of nfse_key',
            ],
            'substitution key' => [fn () => new Substitution('12', '01'), 'access_key must be'],
            'substitution other' => [
                fn () => new Substitution(self::KEY, '99'),
                'reason is required with reason_code 99',
            ],
            'substitution code' => [
                fn () => new Substitution(self::KEY, '07'),
                'reason_code must be one of',
            ],
            'substitution latin1' => [
                fn () => new Substitution(self::KEY, '99', 'motivo com emoji 😀 suficiente'),
                'Latin-1',
            ],
            'intermediary two ids' => [
                fn () => new NfseIntermediary(
                    name: 'I',
                    taxId: '12345678909',
                    foreignId: 'N',
                    address: $supplierAbroad,
                ),
                'exactly one of tax_id',
            ],
            'intermediary national address' => [
                fn () => new NfseIntermediary(name: 'I', foreignId: 'N', address: $national),
                'must be abroad',
            ],
            'intermediary tax id abroad' => [
                fn () => new NfseIntermediary(
                    name: 'I',
                    taxId: '12345678909',
                    address: $supplierAbroad,
                ),
                'not tax_id',
            ],
            'intermediary reason' => [
                fn () => new NfseIntermediary(
                    name: 'I',
                    foreignIdMissingReason: 0,
                    address: $supplierAbroad,
                ),
                'foreign_id_missing_reason must be one of',
            ],
            'address both' => [
                fn () => new PersonAddress(
                    street: 'R',
                    number: '1',
                    neighborhood: 'C',
                    cityCode: '3550308',
                    zipCode: '01001000',
                    countryCode: 'PT',
                ),
                'takes city_code and zip_code',
            ],
            'address abroad BR' => [
                fn () => new PersonAddress(
                    street: 'R',
                    number: '1',
                    neighborhood: 'C',
                    countryCode: 'BR',
                    postalCode: '1',
                    city: 'C',
                    state: 'S',
                ),
                'cannot be BR',
            ],
            'trade service mode' => [
                fn () => $trade(['serviceMode' => 0]),
                'service_mode must be one of',
            ],
            'trade support range' => [
                fn () => $trade(['recipientSupport' => 27]),
                'recipient_support must be 1 to 26',
            ],
            'trade declaration' => [
                fn () => $trade(['temporaryGoods' => 3]),
                'needs export_registration',
            ],
            'trade none with declaration' => [
                fn () => $trade(['importDeclaration' => 'DI']),
                'takes no import_declaration',
            ],
            'government empty' => [fn () => new IbsCbsGovernment(), 'ibs_cbs.government needs'],
            'government type' => [
                fn () => new IbsCbsGovernment('6'),
                'operation_type must be one of',
            ],
            'referenced key' => [
                fn () => new IbsCbs('000', '000001', referencedNfse: ['1']),
                'referenced_nfse[0] must be',
            ],
            'recipient two ids' => [
                fn () => new IbsCbsRecipient(name: 'D', taxId: '12345678909', nif: 'N'),
                'exactly one of tax_id',
            ],
            'recipient address' => [
                fn () => new IbsCbsRecipientAddress('R', '1', neighborhood: 'C'),
                'in Brazil needs city_code and zip_code',
            ],
            'unit place' => [fn () => new RealEstateUnit(), 'takes cib or address'],
            'adjustment type' => [
                fn () => new RealEstateBaseAdjustment('05', 1),
                'type must be one of',
            ],
            'lease percent' => [
                fn () => new RealEstateLease(101, 1),
                'co_ownership_percent must be',
            ],
            'good quantity' => [
                fn () => new MovableGood('84713012', 'N', 0),
                'quantity must be 1 to 999',
            ],
            'regular cst' => [
                fn () => new IbsCbsRegularTaxation('00', '000001'),
                'cst must be 3 digits',
            ],
            'deferral percent' => [fn () => new IbsCbsDeferral(101, 0, 0), 'state_percent must be'],
            'presumed credit' => [
                fn () => new IbsCbs('000', '000001', presumedCredit: '1'),
                'presumed_credit must be 2 digits',
            ],
        ];
    }

    #[DataProvider('refusals')]
    public function testShapeRulesRefuseBeforeTheCall(Closure $build, string $message): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage($message);
        $build();
    }

    public function testCompanyUpdateSendsNfseSettings(): void
    {
        $company = new Company(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($company, self::json([]));
        $company->update(fiscalFields: [
            'approximate_tax_simples_percent' => '4,5',
            'approximate_tax_federal_percent' => '',
            'approximate_tax_state_percent' => '0',
            'approximate_tax_municipal_percent' => '1.25',
            'iss_rate' => '2',
            'municipal_registration' => '12.345-6',
            'special_tax_regime' => '0',
        ]);

        $this->assertSame(['fiscal_fields' => [
            'approximate_tax_simples_percent' => '4.5',
            'approximate_tax_federal_percent' => '',
            'approximate_tax_state_percent' => '0',
            'approximate_tax_municipal_percent' => '1.25',
            'iss_rate' => '2',
            'municipal_registration' => '123456',
            'special_tax_regime' => '0',
        ]], $this->body());
    }

    public function testCompanyUpdateClearsTheSpecialRegimeWithAnEmptyString(): void
    {
        $company = new Company(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($company, self::json([]));
        $company->update(fiscalFields: ['special_tax_regime' => '']);

        $this->assertSame(['fiscal_fields' => ['special_tax_regime' => '']], $this->body());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function badSettings(): array
    {
        return [
            'iss rate zero' => [['iss_rate' => '0']],
            'iss rate above' => [['iss_rate' => '5.01']],
            'simples above' => [['approximate_tax_simples_percent' => '100']],
            'not a number' => [['approximate_tax_state_percent' => 'abc']],
            'places' => [['approximate_tax_federal_percent' => '1.234']],
            'registration' => [['municipal_registration' => '1234567890123456']],
            'regime' => [['special_tax_regime' => '7']],
        ];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('badSettings')]
    public function testCompanyUpdateRefusesBadSettings(array $fields): void
    {
        $company = new Company(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->expectException(InvoiceError::class);
        $company->update(fiscalFields: $fields);
    }

    public function testNbsSuggestionsReadsTheTypedAnswer(): void
    {
        $reference = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($reference, self::json([
            'service_code' => '010700',
            'suggestions' => [[
                'nbs' => '115021000',
                'description' => 'Suporte técnico',
                'operation_indicator' => '100301',
                'ibs_cbs_classification' => '000001',
                'classification_name' => null,
            ]],
            'source' => ['path' => 'data/nfse/anexo-viii.xlsx', 'date' => '2026-10-20'],
        ]));

        $found = $reference->nbsSuggestions('1.07');

        $request = $this->sent[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/service-codes/1.07/nbs', $request->getUri()->getPath());
        $this->assertSame('010700', $found->serviceCode);
        $this->assertSame('data/nfse/anexo-viii.xlsx', $found->source->path);
        $this->assertSame('2026-10-20', $found->source->date);
        $row = $found->suggestions[0];
        $this->assertSame('115021000', $row->nbs);
        $this->assertSame('100301', $row->operationIndicator);
        $this->assertSame('000001', $row->ibsCbsClassification);
        $this->assertNull($row->classificationName);
    }

    public function testNbsSuggestionsAnswersAnEmptyList(): void
    {
        $reference = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($reference, self::json([
            'service_code' => '990101',
            'suggestions' => [],
            'source' => ['path' => 'p', 'date' => null],
        ]));

        $found = $reference->nbsSuggestions('99.01.01');

        $this->assertSame([], $found->suggestions);
        $this->assertNull($found->source->date);
    }

    public function testNbsSuggestionsRefusesUnusableCodes(): void
    {
        $reference = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        foreach (['', '..', '1.07.00.00.00.0'] as $code) {
            try {
                $reference->nbsSuggestions($code);
                $this->fail("expected a refusal for '{$code}'");
            } catch (InvoiceError) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNbsSuggestionsAnswers422AsAnApiError(): void
    {
        $reference = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($reference, new Response(422, [], (string) json_encode(['detail' => 'bad'])));

        $this->expectException(ApiError::class);
        $reference->nbsSuggestions('x');
    }
}
