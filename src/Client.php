<?php

declare(strict_types=1);

namespace Stackin;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Stackin\Errors\ApiError;
use Stackin\Errors\CompanyNotInScopeError;
use Stackin\Errors\CompanyRequiredError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InsufficientPermissionError;

/**
 * Where an api key becomes an HTTP call, for every entry point.
 *
 * Invoice, FiscalReference and Taxpayer all extend it, so one
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

        return new ApiError($status, $text);
    }
}
