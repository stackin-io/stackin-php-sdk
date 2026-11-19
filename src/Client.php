<?php

declare(strict_types=1);

namespace Stackin;

use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Stackin\Errors\ApiError;
use Stackin\Errors\ClientAlreadyExistsError;
use Stackin\Errors\ClientInactiveError;
use Stackin\Errors\ClientNotFoundError;
use Stackin\Errors\CompanyNotInScopeError;
use Stackin\Errors\CompanyRequiredError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InsufficientPermissionError;
use Stackin\Errors\PaymentRequiredError;
use Stackin\Errors\RateLimitedError;

/**
 * Where an api key becomes an HTTP call, for every entry point.
 *
 * Invoice, Clients, FiscalReference and Taxpayer all extend it, so one
 * constructor configures every client and one code path issues every
 * request. A caller never instantiates this.
 */
abstract class Client
{
    public const DEFAULT_BASE_URL = 'https://sdk.stackin.io';
    public const COMPANY_HEADER = 'X-Stackin-Company';

    protected readonly string $baseUrl;
    protected readonly ?string $apiKey;
    protected HttpClient $http;

    /**
     * $country is read by FiscalReference and Taxpayer only. Invoice
     * inherits and ignores it: a fiscal document carries its issuer's
     * country already.
     *
     * $company picks which company a credential that covers several
     * acts for, by its id or its CNPJ, and goes out as the
     * X-Stackin-Company header on every request. Every method also
     * takes one for a single call. When neither is set no header is
     * sent and the request is exactly what it was before; an API key
     * is always one company and never needs it.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        protected readonly string $country = 'BR',
        protected readonly ?string $company = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? self::resolveBaseUrl(), '/');
        $this->apiKey = $apiKey ?? (getenv('STACKIN_API_KEY') ?: null);
        $this->http = new HttpClient(['timeout' => $timeout, 'http_errors' => false]);
    }

    public function country(): string
    {
        return $this->country;
    }

    public function company(): ?string
    {
        return $this->company;
    }

    /**
     * Every company this credential reaches, with the permissions it
     * holds in each: {environment, companies: [{company_id, name,
     * tax_id, permissions}]}. The ids it returns are what company
     * accepts. An API key lists its one company.
     *
     * @return array<string, mixed>
     */
    public function companies(): array
    {
        return $this->request('GET', '/me/companies');
    }

    /**
     * What the account has spent this billing period and what it has
     * left: issued and received documents against the plan's allowance,
     * the price past it and the credit balance (cents) it is taken from.
     */
    public function usage(?string $company = null): Usage
    {
        return Usage::fromArray($this->request('GET', '/company/usage', company: $company));
    }

    protected static function resolveBaseUrl(): string
    {
        $envUrl = getenv('STACKIN_BASE_URL');
        if ($envUrl !== false && $envUrl !== '') {
            return $envUrl;
        }

        return self::DEFAULT_BASE_URL;
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>|null $query
     * @return array<array-key, mixed>
     */
    protected function request(
        string $method,
        string $path,
        ?array $json = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
        ?string $company = null,
    ): array {
        $body = $this->send($method, $path, $json, $query, $idempotencyKey, $company);
        $decoded = $body !== '' ? json_decode($body, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];

        return $decoded['result'] ?? $decoded;
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>|null $query
     */
    protected function send(
        string $method,
        string $path,
        ?array $json = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
        ?string $company = null,
    ): string {
        $url = "{$this->baseUrl}/api/v1{$path}";
        $options = [];
        if ($json !== null) {
            $options['json'] = $json;
        }
        if ($query !== null) {
            $options['query'] = $query;
        }
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $company ??= $this->company;
        if ($company !== null && $company !== '') {
            $headers[self::COMPANY_HEADER] = $company;
        }
        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (GuzzleException $error) {
            throw new ConnectionFailedError($error->getMessage(), $error);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            $decoded = $body !== '' ? json_decode($body, true) : [];
            $decoded = is_array($decoded) ? $decoded : [];
            $detail = $decoded['detail'] ?? $body;
            if ($status === 429) {
                throw self::rateLimited($detail, $decoded, $response->getHeaderLine('Retry-After'));
            }
            throw self::apiError($status, $detail);
        }

        return $body;
    }

    /**
     * The refusals a caller acts on by name get their own type; every
     * other one stays a plain ApiError with the same detail string.
     */
    private static function apiError(int $status, mixed $detail): ApiError
    {
        $text = is_string($detail) ? $detail : (string) json_encode($detail);
        if ($status === 402) {
            return new PaymentRequiredError($status, $text);
        }
        if (!is_array($detail)) {
            return new ApiError($status, $text);
        }

        $companies = $detail['companies'] ?? [];
        $companies = is_array($companies) ? array_values($companies) : [];
        $error = $detail['error'] ?? null;

        if ($status === 422 && $error === 'company_required') {
            return new CompanyRequiredError($status, $text, $companies);
        }
        if ($status === 403 && $error === 'company_not_in_scope') {
            return new CompanyNotInScopeError($status, $text, $companies);
        }
        if ($status === 403 && ($detail['code'] ?? null) === 'INSUFFICIENT_PERMISSION') {
            $description = $detail['error_description'] ?? '';

            return new InsufficientPermissionError(
                $status,
                $text,
                is_string($description) ? $description : '',
            );
        }

        if ($status === 409 && ($detail['code'] ?? null) === 'CLIENT_EXISTS') {
            $clientId = $detail['client_id'] ?? '';

            return new ClientAlreadyExistsError(
                $status,
                $text,
                is_string($clientId) ? $clientId : '',
            );
        }

        $code = $detail['code'] ?? null;
        $clientId = $detail['client_id'] ?? '';
        $clientId = is_string($clientId) ? $clientId : '';
        if ($status === 422 && $code === 'CLIENT_INACTIVE') {
            return new ClientInactiveError($status, $text, $clientId);
        }
        if ($status === 404 && $code === 'CLIENT_NOT_FOUND') {
            return new ClientNotFoundError($status, $text, $clientId);
        }

        return new ApiError($status, $text);
    }

    /**
     * Any 429. code and retry_at are read from the body's top level, or
     * from detail when the API nests them there; Retry-After from the
     * header. Each is null when absent or unreadable.
     *
     * @param array<array-key, mixed> $body
     */
    private static function rateLimited(
        mixed $detail,
        array $body,
        string $retryAfter,
    ): RateLimitedError {
        $fields = is_array($detail) ? $detail + $body : $body;
        $message = $fields['message'] ?? $detail;
        $text = is_string($message) ? $message : (string) json_encode($detail);

        $code = $fields['code'] ?? null;
        $retryAt = $fields['retry_at'] ?? null;
        $seconds = trim($retryAfter);

        return new RateLimitedError(
            429,
            $text,
            ctype_digit($seconds) ? (int) $seconds : null,
            is_string($retryAt) ? self::moment($retryAt) : null,
            is_string($code) ? $code : null,
        );
    }

    private static function moment(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
