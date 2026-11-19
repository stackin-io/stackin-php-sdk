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
use Stackin\Errors\RateLimitedError;
use Stackin\Invoice;

final class ReceivedSyncTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $sent = [];

    private function invoice(Response $response): Invoice
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->sent));

        $client = new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    public function testSyncPostsWithoutABody(): void
    {
        $client = $this->invoice(new Response(200, [], (string) json_encode([
            'stored' => 0,
            'pages' => 1,
            'last_nsu' => '0',
            'max_nsu' => null,
            'next_sync_at' => null,
        ])));

        $client->syncReceived();

        $request = $this->sent[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://sdk.test/api/v1/received-invoices/sync',
            (string) $request->getUri(),
        );
        self::assertSame('', (string) $request->getBody());
    }

    public function testSyncReadsTheResult(): void
    {
        $client = $this->invoice(new Response(200, [], (string) json_encode([
            'stored' => 7,
            'pages' => 2,
            'last_nsu' => '000000000000123',
            'max_nsu' => '000000000000123',
            'next_sync_at' => '2026-11-18T22:00:00Z',
            'unknown' => true,
        ])));

        $sync = $client->syncReceived();

        self::assertSame(7, $sync->stored);
        self::assertSame(2, $sync->pages);
        self::assertSame('000000000000123', $sync->lastNsu);
        self::assertSame('000000000000123', $sync->maxNsu);
        self::assertNotNull($sync->nextSyncAt);
        self::assertSame('2026-11-18T22:00:00+00:00', $sync->nextSyncAt->format(DATE_ATOM));
    }

    public function testSyncToleratesNulls(): void
    {
        $client = $this->invoice(new Response(200, [], (string) json_encode([
            'stored' => 0,
            'pages' => 0,
            'last_nsu' => '5',
            'max_nsu' => null,
            'next_sync_at' => null,
        ])));

        $sync = $client->syncReceived();

        self::assertNull($sync->maxNsu);
        self::assertNull($sync->nextSyncAt);
    }

    public function testSyncInsideTheCooldownRaisesRateLimited(): void
    {
        $client = $this->invoice(new Response(429, ['Retry-After' => '1800'], (string) json_encode([
            'detail' => 'SEFAZ asks to wait one hour',
            'code' => 'DISTRIBUTION_COOLDOWN',
            'retry_at' => '2026-11-18T22:00:00Z',
        ])));

        try {
            $client->syncReceived();
            self::fail('expected RateLimitedError');
        } catch (RateLimitedError $error) {
            self::assertInstanceOf(ApiError::class, $error);
            self::assertSame(429, $error->statusCode);
            self::assertSame('SEFAZ asks to wait one hour', $error->detail);
            self::assertSame(1800, $error->retryAfter);
            self::assertSame('DISTRIBUTION_COOLDOWN', $error->errorCode);
            self::assertNotNull($error->retryAt);
            self::assertSame('2026-11-18T22:00:00+00:00', $error->retryAt->format(DATE_ATOM));
        }
    }
}
