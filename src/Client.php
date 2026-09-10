<?php

declare(strict_types=1);

namespace Stackin;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;

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

    protected readonly string $baseUrl;
    protected readonly ?string $apiKey;
    protected HttpClient $http;

    /**
     * $country is read by FiscalReference and Taxpayer only. Invoice
     * inherits and ignores it: a fiscal document carries its issuer's
     * country already.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        protected readonly string $country = 'BR',
    ) {
        $this->baseUrl = rtrim($baseUrl ?? self::resolveBaseUrl(), '/');
        $this->apiKey = $apiKey ?? (getenv('STACKIN_API_KEY') ?: null);
        $this->http = new HttpClient(['timeout' => $timeout, 'http_errors' => false]);
    }

    public function country(): string
    {
        return $this->country;
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
    ): array {
        $body = $this->send($method, $path, $json, $query, $idempotencyKey);
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
            throw new ApiError($status, is_string($detail) ? $detail : json_encode($detail));
        }

        return $body;
    }
}
