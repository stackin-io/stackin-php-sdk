<?php

declare(strict_types=1);

namespace Stackin\Tests\Errors;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Client;
use Stackin\Errors\ApiError;
use Stackin\Errors\ClientAlreadyExistsError;
use Stackin\Errors\ClientInactiveError;
use Stackin\Errors\ClientNotFoundError;
use Stackin\Errors\CompanyRequiredError;
use Stackin\Errors\PaymentRequiredError;
use Stackin\Errors\RateLimitedError;
use Stackin\Invoice;
use Throwable;

final class MappingTest extends TestCase
{
    /**
     * @param array<array-key, mixed>|string $body
     * @param array<string, string> $headers
     */
    private static function raised(int $status, array|string $body, array $headers = []): Throwable
    {
        $raw = is_string($body) ? $body : (string) json_encode($body);
        $client = new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test');
        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => HandlerStack::create(new MockHandler([
                new Response($status, $headers, $raw),
            ])),
            'http_errors' => false,
        ]));

        try {
            $client->history();
        } catch (Throwable $error) {
            return $error;
        }
        self::fail('expected an error');
    }

    public function testA402IsPaymentRequired(): void
    {
        $error = self::raised(402, ['detail' => 'Not enough credit']);

        self::assertInstanceOf(PaymentRequiredError::class, $error);
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame(402, $error->statusCode);
        self::assertSame('Not enough credit', $error->detail);
    }

    public function testAny429IsRateLimited(): void
    {
        $error = self::raised(429, ['detail' => 'Too many requests'], ['Retry-After' => '30']);

        self::assertInstanceOf(RateLimitedError::class, $error);
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('Too many requests', $error->detail);
        self::assertSame(30, $error->retryAfter);
        self::assertNull($error->retryAt);
        self::assertNull($error->errorCode);
    }

    public function testA429WithoutRetryAfterOrJson(): void
    {
        $error = self::raised(429, 'slow down');

        self::assertInstanceOf(RateLimitedError::class, $error);
        self::assertSame('slow down', $error->detail);
        self::assertNull($error->retryAfter);
    }

    public function testA429WithTheFieldsNestedInDetail(): void
    {
        $error = self::raised(429, ['detail' => [
            'code' => 'DISTRIBUTION_COOLDOWN',
            'message' => 'wait',
            'retry_at' => '2026-11-18T22:00:00Z',
        ]]);

        self::assertInstanceOf(RateLimitedError::class, $error);
        self::assertSame('wait', $error->detail);
        self::assertSame('DISTRIBUTION_COOLDOWN', $error->errorCode);
        self::assertNotNull($error->retryAt);
    }

    public function testAnUnreadableRetryAtIsNull(): void
    {
        $error = self::raised(
            429,
            ['detail' => 'x', 'retry_at' => 'soon'],
            ['Retry-After' => 'Wed, 18 Nov'],
        );

        self::assertInstanceOf(RateLimitedError::class, $error);
        self::assertNull($error->retryAt);
        self::assertNull($error->retryAfter);
    }

    public function testA422ClientInactive(): void
    {
        $error = self::raised(422, ['detail' => [
            'code' => 'CLIENT_INACTIVE',
            'message' => 'Client is inactive',
            'client_id' => 'cl_1',
        ]]);

        self::assertInstanceOf(ClientInactiveError::class, $error);
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame(422, $error->statusCode);
        self::assertSame('cl_1', $error->clientId);
    }

    public function testA404ClientNotFound(): void
    {
        $error = self::raised(404, ['detail' => [
            'code' => 'CLIENT_NOT_FOUND',
            'message' => 'Client not found',
            'client_id' => 'cl_2',
        ]]);

        self::assertInstanceOf(ClientNotFoundError::class, $error);
        self::assertInstanceOf(ApiError::class, $error);
        self::assertSame('cl_2', $error->clientId);
    }

    public function testAPlain404StaysApiError(): void
    {
        $error = self::raised(404, ['detail' => 'Invoice not found']);

        self::assertSame(ApiError::class, $error::class);
    }

    public function testAnother422StaysApiError(): void
    {
        $error = self::raised(422, ['detail' => ['code' => 'CITY_NOT_FOUND']]);

        self::assertSame(ApiError::class, $error::class);
    }

    public function testTheExistingTypedErrorsStillMap(): void
    {
        self::assertInstanceOf(
            CompanyRequiredError::class,
            self::raised(422, ['detail' => ['error' => 'company_required', 'companies' => []]]),
        );
        self::assertInstanceOf(
            ClientAlreadyExistsError::class,
            self::raised(409, ['detail' => ['code' => 'CLIENT_EXISTS', 'client_id' => 'c']]),
        );
    }
}
