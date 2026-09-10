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
use Stackin\Client;
use Stackin\Errors\ApiError;
use Stackin\Errors\InvoiceError;
use Stackin\FiscalReference;
use Stackin\Kind;

final class FiscalReferenceTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $sent = [];

    private function inject(Client $client, MockHandler $mock): void
    {
        $this->sent = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->sent));

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));
    }

    /**
     * @param array<string, mixed>|list<string> $body
     */
    private function client(array $body = [], int $status = 200): FiscalReference
    {
        $client = new FiscalReference(apiKey: 'k', baseUrl: 'https://sdk.test');
        $this->inject($client, new MockHandler([
            new Response($status, [], (string) json_encode($body)),
        ]));

        return $client;
    }

    /** @return array<string, string> */
    private function query(): array
    {
        parse_str($this->sent[0]['request']->getUri()->getQuery(), $parsed);

        /** @var array<string, string> $parsed */
        return $parsed;
    }

    public function testEveryDocumentedKindHasAnAccessor(): void
    {
        $client = new FiscalReference(apiKey: 'k');

        $accessors = [
            'cfop' => $client->cfop,
            'ncm' => $client->ncm,
            'cest' => $client->cest,
            'cst' => $client->cst,
            'csosn' => $client->csosn,
            'iss_service' => $client->issService,
            'icms_fuel' => $client->icmsFuel,
            'ibs_cbs_class' => $client->ibsCbsClass,
        ];

        self::assertSame(FiscalReference::KINDS, array_keys($accessors));
        foreach ($accessors as $name => $kind) {
            self::assertInstanceOf(Kind::class, $kind);
            self::assertSame($name, $kind->name);
        }
    }

    public function testAKindWithNoAccessorIsStillReachable(): void
    {
        $client = new FiscalReference(apiKey: 'k');

        self::assertSame(
            'published_tomorrow',
            $client->kind('published_tomorrow')->name,
        );
    }

    public function testGetAsksForTheOneCode(): void
    {
        $client = $this->client(['code' => '84716052']);

        $client->ncm->get('84716052');

        self::assertSame(
            '/api/v1/fiscal-references/ncm/84716052',
            $this->sent[0]['request']->getUri()->getPath(),
        );
        self::assertSame('BR', $this->query()['country']);
    }

    public function testAMissingCodeIsAnApiErrorNotANewType(): void
    {
        $client = $this->client(['detail' => 'no'], 404);

        $this->expectException(ApiError::class);
        $client->cfop->get('9999');
    }

    public function testSearchPinsTheKindItWasReachedThrough(): void
    {
        $client = $this->client(['data' => []]);

        $client->ncm->search('teclado');

        self::assertSame('ncm', $this->query()['kind']);
        self::assertSame('teclado', $this->query()['search']);
    }

    public function testAnEmptyTermIsNotSentAsAnEmptySearch(): void
    {
        $client = $this->client(['data' => []]);

        $client->cfop->search('');

        self::assertArrayNotHasKey('search', $this->query());
    }

    public function testSearchingTheClientItselfPinsNoKind(): void
    {
        $client = $this->client(['data' => []]);

        $client->search('teclado');

        self::assertArrayNotHasKey('kind', $this->query());
        self::assertSame('teclado', $this->query()['search']);
    }

    public function testSearchSendsNoOrderingTheRouteWouldDiscard(): void
    {
        $client = $this->client(['data' => []]);

        $client->ncm->search(limit: 10, offset: 20);

        self::assertSame('10', $this->query()['limit']);
        self::assertSame('20', $this->query()['offset']);
        self::assertArrayNotHasKey('sort_by', $this->query());
        self::assertArrayNotHasKey('order_by', $this->query());
    }

    public function testCountryDefaultsToBrAndReachesEveryAccessor(): void
    {
        self::assertSame('BR', (new FiscalReference(apiKey: 'k'))->country());

        $client = new FiscalReference(apiKey: 'k', country: 'AR');
        self::assertSame('AR', $client->ncm->country);
    }

    public function testOneCallCanOverrideTheCountry(): void
    {
        $client = $this->client();

        $client->ncm->get('1', 'PY');

        self::assertSame('PY', $this->query()['country']);
    }

    public function testKindsAsksTheApiRatherThanAnsweringFromTheConstant(): void
    {
        $client = $this->client(['ncm', 'brand_new']);

        $kinds = $client->kinds();

        self::assertSame(
            '/api/v1/fiscal-references/kinds',
            $this->sent[0]['request']->getUri()->getPath(),
        );
        self::assertContains('brand_new', $kinds);
    }

    /**
     * A code the caller types by hand must not rewrite the path.
     */
    public function testASlashInACodeStaysInsideItsSegment(): void
    {
        $client = $this->client();

        $client->ncm->get('8471/60/52');

        self::assertSame(
            '/api/v1/fiscal-references/ncm/8471%2F60%2F52',
            $this->sent[0]['request']->getUri()->getPath(),
        );
    }

    public function testAKindCannotClimbOutOfItsEndpoint(): void
    {
        $client = new FiscalReference(apiKey: 'k');

        $this->expectException(InvoiceError::class);
        $client->kind('..')->get('kinds');
    }

    public function testAnEmptyCodeIsRefusedRatherThanDropped(): void
    {
        $client = new FiscalReference(apiKey: 'k');

        $this->expectException(InvoiceError::class);
        $client->ncm->get('');
    }
}
