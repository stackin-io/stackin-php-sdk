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
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Stackin\Client;
use Stackin\Errors\ApiError;
use Stackin\Errors\InvoiceError;
use Stackin\Taxpayer;

final class TaxpayerTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @param array<string, mixed> $body
     */
    private function client(array $body = [], int $status = 200): Taxpayer
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, [], (string) json_encode($body)),
        ]));
        $stack->push(Middleware::history($this->sent));

        $client = new Taxpayer(apiKey: 'k', baseUrl: 'https://sdk.test');

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
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

    public function testGetLooksUpTheExactTaxId(): void
    {
        $client = $this->client(['name' => 'ACME']);

        $client->get('00000000000191');

        self::assertSame(
            '/api/v1/taxpayers/00000000000191',
            $this->sent[0]['request']->getUri()->getPath(),
        );
        self::assertSame('BR', $this->query()['country']);
    }

    public function testAnUnknownTaxIdIsAnApiError(): void
    {
        $client = $this->client(['detail' => 'no'], 404);

        $this->expectException(ApiError::class);
        $client->get('00000000000000');
    }

    public function testOneCallCanOverrideTheCountry(): void
    {
        $client = $this->client();

        $client->get('1', 'AR');

        self::assertSame('AR', $this->query()['country']);
    }

    /**
     * A search here would be a bulk export of real people. The API
     * refuses it at the route, but that is the smaller reason: this
     * type is thin on purpose, and this test is what keeps a
     * well-meaning "parity with FiscalReference" out.
     */
    public function testItDeclaresExactlyOneMethodOfItsOwn(): void
    {
        $own = [];
        $public = (new ReflectionClass(Taxpayer::class))
            ->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($public as $method) {
            if ($method->getDeclaringClass()->getName() === Taxpayer::class) {
                $own[] = $method->getName();
            }
        }

        self::assertSame(['get'], $own);
    }

    /**
     * A CNPJ is displayed with a slash; it must not rewrite the path.
     */
    public function testAFormattedCnpjStaysInsideItsSegment(): void
    {
        $client = $this->client();

        $client->get('00.000.000/0001-91');

        self::assertSame(
            '/api/v1/taxpayers/00.000.000%2F0001-91',
            $this->sent[0]['request']->getUri()->getPath(),
        );
    }

    public function testAnEmptyTaxIdIsRefusedRatherThanDropped(): void
    {
        $client = new Taxpayer(apiKey: 'k');

        $this->expectException(InvoiceError::class);
        $client->get('');
    }
}
