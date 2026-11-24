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
use Stackin\Webhooks;

final class WebhooksTest extends TestCase
{
    private const BASE = 'https://sdk.test/api/v1';
    private const SECRET = 'a-secret-of-16-chars';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    private function hooks(Response $response): Webhooks
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->sent));

        $client = new Webhooks(apiKey: 'k', baseUrl: 'https://sdk.test');
        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private static function json(array $body, int $status = 200): Response
    {
        return new Response($status, [], (string) json_encode($body));
    }

    private function request(): Request
    {
        $this->assertCount(1, $this->sent);

        return $this->sent[0]['request'];
    }

    private function url(): string
    {
        $uri = $this->request()->getUri();

        return $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath();
    }

    public function testCreateSendsUrlSecretAndEvents(): void
    {
        $endpoint = $this->hooks(self::json(['id' => 'wh_1'], 201))->create(
            'https://shop.example/stackin',
            self::SECRET,
            events: ['invoice.authorized', 'invoice.rejected'],
            company: 'acme',
        );

        $this->assertSame('POST', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/webhooks', $this->url());
        $this->assertSame([
            'url' => 'https://shop.example/stackin',
            'secret' => self::SECRET,
            'events' => ['invoice.authorized', 'invoice.rejected'],
        ], json_decode((string) $this->request()->getBody(), true));
        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
        $this->assertSame('wh_1', $endpoint['id']);
    }

    public function testCreateAcceptsTheIntegrationEvents(): void
    {
        $events = [
            'integration.settings_changed',
            'integration.order_retry',
            'integration.order_issue',
            'integration.catalog_sync',
        ];

        $this->hooks(self::json(['id' => 'wh_1'], 201))
            ->create('https://shop.example/stackin', self::SECRET, events: $events);

        $this->assertSame([
            'url' => 'https://shop.example/stackin',
            'secret' => self::SECRET,
            'events' => $events,
        ], json_decode((string) $this->request()->getBody(), true));
    }

    public function testCreateWithoutEventsLeavesThemOut(): void
    {
        $this->hooks(self::json(['id' => 'wh_1'], 201))
            ->create('HTTPS://shop.example/x', self::SECRET);

        $body = json_decode((string) $this->request()->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayNotHasKey('events', $body);
    }

    /**
     * @return list<array{string, string, ?list<string>, string}>
     */
    public static function refusals(): array
    {
        return [
            ['http://shop.example/x', self::SECRET, null, 'https://'],
            ['https://shop.example/' . str_repeat('a', 500), self::SECRET, null, 'at most 500'],
            ['https://shop.example/x', 'too-short', null, '16 to 255'],
            ['https://shop.example/x', str_repeat('s', 256), null, '16 to 255'],
            [
                'https://shop.example/x',
                self::SECRET,
                ['invoice.issued'],
                'unknown events: invoice.issued',
            ],
        ];
    }

    /**
     * @param list<string>|null $events
     */
    #[DataProvider('refusals')]
    public function testCreateRefusesLocally(
        string $url,
        string $secret,
        ?array $events,
        string $message,
    ): void {
        $hooks = $this->hooks(self::json([]));

        try {
            $hooks->create($url, $secret, events: $events);
            $this->fail('expected InvoiceError');
        } catch (InvoiceError $error) {
            $this->assertStringContainsString($message, $error->getMessage());
            $this->assertSame([], $this->sent);
        }
    }

    public function testEventsAreTheApiOnes(): void
    {
        $this->assertSame(
            [
                'invoice.authorized',
                'invoice.rejected',
                'invoice.cancelled',
                'invoice.corrected',
                'received_invoice.stored',
                'received_invoice.full_document',
                'integration.settings_changed',
                'integration.order_retry',
                'integration.order_issue',
                'integration.catalog_sync',
            ],
            Webhooks::EVENTS,
        );
    }

    public function testListReturnsAPlainList(): void
    {
        $rows = [['id' => 'wh_1', 'url' => 'https://shop.example/x']];
        $result = $this->hooks(self::json($rows))->list(company: 'acme');

        $this->assertSame('GET', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/webhooks', $this->url());
        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
        $this->assertSame($rows, $result);
    }

    public function testDeleteHandlesTheEmpty204(): void
    {
        $this->hooks(new Response(204))->delete('wh/1');

        $this->assertSame('DELETE', $this->request()->getMethod());
        $this->assertSame('/api/v1/webhooks/wh%2F1', $this->request()->getUri()->getPath());
    }

    public function testDeleteRefusesADotSegment(): void
    {
        $hooks = $this->hooks(new Response(204));

        $this->expectException(InvoiceError::class);
        $hooks->delete('..');
    }

    public function testDeleteOfAnUnknownIdIsAnApiError(): void
    {
        $hooks = $this->hooks(self::json(['detail' => 'webhook not found'], 404));

        try {
            $hooks->delete('wh_9');
            $this->fail('expected ApiError');
        } catch (ApiError $error) {
            $this->assertSame(404, $error->statusCode);
        }
    }

    public function testDeliveriesListsAPage(): void
    {
        $result = $this->hooks(self::json(['items' => [], 'total' => 0]))
            ->deliveries(limit: 20, orderBy: 'desc');

        $this->assertSame('GET', $this->request()->getMethod());
        $this->assertSame(self::BASE . '/webhooks/deliveries', $this->url());
        $this->assertSame('limit=20&order_by=desc', $this->request()->getUri()->getQuery());
        $this->assertSame(0, $result['total']);
    }
}
