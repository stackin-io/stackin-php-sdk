<?php

declare(strict_types=1);

namespace Stackin;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Stackin\Br\Product;
use Stackin\Errors\ApiError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InvoiceError;

/**
 * Client for issuing, consulting, and cancelling fiscal documents.
 *
 * No SDK constructor ever takes an issuer's CNPJ, address, tax
 * regime, or certificate — those live entirely in the dashboard/API
 * account tied to the api_key.
 */
final class Invoice
{
    public const DEFAULT_BASE_URL = 'https://sdk.stackin.io';

    private readonly string $baseUrl;
    private readonly ?string $apiKey;
    private HttpClient $http;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? self::resolveBaseUrl(), '/');
        $this->apiKey = $apiKey ?? (getenv('STACKIN_API_KEY') ?: null);
        $this->http = new HttpClient(['timeout' => $timeout, 'http_errors' => false]);
    }

    private static function resolveBaseUrl(): string
    {
        $envUrl = getenv('STACKIN_BASE_URL');
        if ($envUrl !== false && $envUrl !== '') {
            return $envUrl;
        }

        return self::DEFAULT_BASE_URL;
    }

    /**
     * Issues a fiscal document.
     *
     * @param Product[] $items
     * @return array<string, mixed>
     */
    public function issue(
        DocumentType $documentType,
        string $clientName,
        string $taxId,
        array $items,
        ?Address $recipientAddress = null,
        ?string $series = null,
        ?string $number = null,
    ): array {
        if ($items === []) {
            throw new InvoiceError("items can't be empty");
        }

        if ($documentType === DocumentType::NFE) {
            foreach ($items as $index => $item) {
                if (!$item->ncm) {
                    throw new InvoiceError("items[{$index}].ncm is required for NFE");
                }
                if (!$item->cfop) {
                    throw new InvoiceError("items[{$index}].cfop is required for NFE");
                }
            }
        }

        $payload = [
            'document_type' => $documentType->value,
            'client_name' => $clientName,
            'tax_id' => $taxId,
            'items' => array_map(static fn (Product $item): array => $item->toArray(), $items),
        ];
        if ($recipientAddress !== null) {
            $payload['recipient_address'] = $recipientAddress->toArray();
        }
        if ($series !== null) {
            $payload['series'] = $series;
        }
        if ($number !== null) {
            $payload['number'] = $number;
        }

        return $this->request('POST', '/invoices', json: $payload);
    }

    /**
     * Consults a fiscal document by its access key.
     *
     * @return array<string, mixed>
     */
    public function consult(string $accessKey, DocumentType $documentType): array
    {
        return $this->request(
            'GET',
            "/invoices/{$accessKey}",
            query: ['document_type' => $documentType->value],
        );
    }

    /**
     * Cancels a fiscal document by its access key.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $accessKey, DocumentType $documentType, string $reason): array
    {
        $payload = [
            'document_type' => $documentType->value,
            'reason' => $reason,
        ];

        return $this->request('POST', "/invoices/{$accessKey}/cancel", json: $payload);
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, mixed>|null $query
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        ?array $json = null,
        ?array $query = null,
    ): array {
        $url = "{$this->baseUrl}/api/v1{$path}";
        $options = [];
        if ($json !== null) {
            $options['json'] = $json;
        }
        if ($query !== null) {
            $options['query'] = $query;
        }
        if ($this->apiKey) {
            $options['headers'] = ['Authorization' => "Bearer {$this->apiKey}"];
        }

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (GuzzleException $error) {
            throw new ConnectionFailedError($error->getMessage(), $error);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = $body !== '' ? json_decode($body, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status < 200 || $status >= 300) {
            $detail = $decoded['detail'] ?? $body;
            throw new ApiError($status, is_string($detail) ? $detail : json_encode($detail));
        }

        return $decoded['result'] ?? $decoded;
    }
}
