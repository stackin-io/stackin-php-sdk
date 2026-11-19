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
use Stackin\Clients;
use Stackin\Invoice;
use Stackin\Usage;

final class UsageTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @template T of Client
     * @param T $client
     * @param array<array-key, mixed> $body
     * @return T
     */
    private function mocked(Client $client, array $body): Client
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode($body)),
        ]));
        $stack->push(Middleware::history($this->sent));

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    private static function invoice(): Invoice
    {
        return new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
    }

    /**
     * @return array<string, mixed>
     */
    private static function full(): array
    {
        return [
            'plan_name' => 'Pro',
            'plan_slug' => 'pro',
            'included_invoices' => 500,
            'used_invoices' => 120,
            'overage_price_cents' => 30,
            'included_received' => 50,
            'used_received' => 61,
            'received_price_cents' => 5,
            'received_free' => true,
            'current_period_start' => '2026-11-01T00:00:00Z',
            'current_period_end' => '2026-12-01T00:00:00Z',
            'credit_balance' => 12345,
            'companies_on_account' => 3,
        ];
    }

    public function testUsageGetsCompanyUsage(): void
    {
        $client = $this->mocked(self::invoice(), self::full());

        $client->usage();

        $request = $this->sent[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://sdk.test/api/v1/company/usage', (string) $request->getUri());
    }

    public function testUsageReadsEveryField(): void
    {
        $client = $this->mocked(self::invoice(), self::full());

        $usage = $client->usage();

        self::assertSame('Pro', $usage->planName);
        self::assertSame('pro', $usage->planSlug);
        self::assertSame(500, $usage->includedInvoices);
        self::assertSame(120, $usage->usedInvoices);
        self::assertSame(30, $usage->overagePriceCents);
        self::assertSame(50, $usage->includedReceived);
        self::assertSame(61, $usage->usedReceived);
        self::assertSame(5, $usage->receivedPriceCents);
        self::assertTrue($usage->receivedFree);
        self::assertSame(
            '2026-11-01T00:00:00+00:00',
            $usage->currentPeriodStart->format(DATE_ATOM),
        );
        self::assertNotNull($usage->currentPeriodEnd);
        self::assertSame('2026-12-01T00:00:00+00:00', $usage->currentPeriodEnd->format(DATE_ATOM));
        self::assertSame(12345, $usage->creditBalance);
        self::assertSame(3, $usage->companiesOnAccount);
    }

    public function testUsageToleratesNullsAndUnknownFields(): void
    {
        $body = self::full();
        $body['overage_price_cents'] = null;
        $body['current_period_end'] = null;
        $body['something_new'] = ['x' => 1];

        $usage = Usage::fromArray($body);

        self::assertNull($usage->overagePriceCents);
        self::assertNull($usage->currentPeriodEnd);
    }

    public function testUsageDefaultsTheReceivedFieldsWhenAbsent(): void
    {
        $body = self::full();
        unset(
            $body['included_received'],
            $body['used_received'],
            $body['received_price_cents'],
            $body['received_free'],
        );

        $usage = Usage::fromArray($body);

        self::assertSame(50, $usage->includedReceived);
        self::assertSame(0, $usage->usedReceived);
        self::assertSame(5, $usage->receivedPriceCents);
        self::assertFalse($usage->receivedFree);
    }

    public function testUsageSendsTheCompanyHeader(): void
    {
        $clients = new Clients(apiKey: 'k', baseUrl: 'https://sdk.test');
        $client = $this->mocked($clients, self::full());

        $client->usage(company: 'c1');

        $request = $this->sent[0]['request'];
        self::assertSame('c1', $request->getHeaderLine(Client::COMPANY_HEADER));
    }
}
