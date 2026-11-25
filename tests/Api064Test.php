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
use Stackin\Br\Product;
use Stackin\DocumentType;
use Stackin\FiscalReference;
use Stackin\Invoice;

final class Api064Test extends TestCase
{
    private MockHandler $mock;

    /**
     * @param array<array-key, mixed> $body
     */
    private function inject(object $client, string $class, array $body): void
    {
        $this->mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body)),
        ]);
        $property = new ReflectionProperty($class, 'http');
        $property->setValue($client, new HttpClient([
            'handler' => HandlerStack::create($this->mock),
            'http_errors' => false,
        ]));
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function invoice(array $body = ['result' => []]): Invoice
    {
        $invoice = new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($invoice, Invoice::class, $body);

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

    private function issue(Invoice $invoice, ?DateTimeImmutable $started, ?string $reason): void
    {
        $invoice->issue(
            DocumentType::NFE,
            'Acme',
            '12345678909',
            [new Product(description: 'Widget', amount: 10.0, ncm: '84713012', cfop: '5102')],
            new Address(
                state: 'SC',
                street: 'Rua das Flores',
                number: '1200',
                neighborhood: 'Centro',
                city: 'Joinville',
                zipCode: '89201100',
            ),
            contingency: true,
            contingencyStartedAt: $started,
            contingencyReason: $reason,
        );
    }

    public function testNbsSuggestionReadsTheNewFields(): void
    {
        $reference = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($reference, FiscalReference::class, [
            'service_code' => '010700',
            'suggestions' => [[
                'nbs' => '115021000',
                'description' => 'Suporte técnico',
                'operation_indicator' => '100301',
                'ibs_cbs_classification' => '200034',
                'classification_name' => 'Redução',
                'classification_cst' => '200',
                'reduction_ibs' => 60,
                'reduction_cbs' => null,
                'legal_reference' => 'Art. 129 da LC 214/2025',
                'incidence' => 'Local do fornecedor',
                'operation_variants' => ['free_abroad' => '100401', 'onerous_domestic' => '100301'],
            ]],
            'source' => ['path' => 'data/nfse/anexo-viii.xlsx', 'date' => null],
        ]);

        $row = $reference->nbsSuggestions('1.07')->suggestions[0];

        $this->assertSame('200', $row->classificationCst);
        $this->assertSame(60.0, $row->reductionIbs);
        $this->assertNull($row->reductionCbs);
        $this->assertSame('Art. 129 da LC 214/2025', $row->legalReference);
        $this->assertSame('Local do fornecedor', $row->incidence);
        $this->assertSame('100401', $row->operationVariants['free_abroad'] ?? null);
    }

    public function testNbsSuggestionLeavesTheNewFieldsNullWhenAbsent(): void
    {
        $reference = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($reference, FiscalReference::class, [
            'service_code' => '010700',
            'suggestions' => [['nbs' => '115021000']],
            'source' => ['path' => 'x', 'date' => null],
        ]);

        $row = $reference->nbsSuggestions('1.07')->suggestions[0];

        $this->assertNull($row->classificationCst);
        $this->assertNull($row->reductionIbs);
        $this->assertNull($row->operationVariants);
    }

    public function testContingencyMomentAndReasonReachTheBody(): void
    {
        $started = new DateTimeImmutable('2026-11-25 09:00:00', new DateTimeZone('-03:00'));
        $this->issue($this->invoice(), $started, 'SEFAZ authorizer unavailable');

        $body = $this->sentBody();
        $this->assertTrue($body['contingency']);
        $this->assertSame('2026-11-25T09:00:00-03:00', $body['contingency_started_at']);
        $this->assertSame('SEFAZ authorizer unavailable', $body['contingency_reason']);
    }

    public function testContingencyMomentAndReasonAreLeftOutWhenNull(): void
    {
        $this->issue($this->invoice(), null, null);

        $body = $this->sentBody();
        $this->assertArrayNotHasKey('contingency_started_at', $body);
        $this->assertArrayNotHasKey('contingency_reason', $body);
    }

    public function testValidateTakesThemToo(): void
    {
        $invoice = $this->invoice(['valid' => true, 'errors' => [], 'warnings' => []]);
        $invoice->validate(
            DocumentType::NFSE,
            'Acme',
            '12345678909',
            [new Product(description: 'Servico', amount: 10.0, unitPrice: 10.0)],
            contingencyReason: 'SEFAZ authorizer unavailable',
        );

        $this->assertSame('SEFAZ authorizer unavailable', $this->sentBody()['contingency_reason']);
    }

    public function testClassifySendsTheLanguageAndTheOriginAnswer(): void
    {
        $invoice = $this->invoice(['result' => []]);
        $invoice->classify(
            'Camiseta de algodao',
            DocumentType::NFE,
            answers: ['origin' => 'resale'],
            acceptLanguage: 'en',
        );

        $request = $this->mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('en', $request->getHeaderLine('Accept-Language'));
        $this->assertSame(['origin' => 'resale'], $this->sentBody()['answers']);
        $this->assertArrayNotHasKey('accept_language', $this->sentBody());
    }

    public function testReceivedSendsTheLanguage(): void
    {
        $invoice = $this->invoice(['data' => []]);
        $invoice->received(limit: 5, acceptLanguage: 'es');

        $request = $this->mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('es', $request->getHeaderLine('Accept-Language'));
        $this->assertSame('limit=5', $request->getUri()->getQuery());
    }

    public function testReceivedSendsNoLanguageWhenUnset(): void
    {
        $this->invoice(['data' => []])->received();

        $request = $this->mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertFalse($request->hasHeader('Accept-Language'));
    }
}
