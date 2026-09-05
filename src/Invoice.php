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
     * Rejects a missing or partial buyer address before the network call —
     * the SEFAZ rejects a partial enderDest (274/726/696/695).
     */
    private static function validateNfeAddress(?Address $address): void
    {
        if ($address === null) {
            throw new InvoiceError('recipientAddress is required for NFE');
        }

        $fields = [
            'street' => $address->street,
            'number' => $address->number,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
            'zipCode' => $address->zipCode,
            'cityCode' => $address->cityCode,
        ];

        $missing = array_keys(array_filter(
            $fields,
            static fn (?string $value): bool => $value === null || $value === '',
        ));

        if ($missing !== []) {
            throw new InvoiceError(
                'recipientAddress is missing required fields for NFE: ' . implode(', ', $missing),
            );
        }
    }

    /**
     * Issues a fiscal document.
     *
     * Pass $idempotencyKey to make a retry safe: the same key with the same
     * body replays the first response instead of issuing a second document.
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
        ?string $idempotencyKey = null,
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
            self::validateNfeAddress($recipientAddress);
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

        return $this->request(
            'POST',
            '/invoices',
            json: $payload,
            idempotencyKey: $idempotencyKey,
        );
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
     * Reports a reserved but never used NFE numbering range.
     *
     * @return array<string, mixed>
     */
    public function invalidate(
        string $series,
        int $numberStart,
        int $numberEnd,
        string $reason,
    ): array {
        $length = mb_strlen($reason);
        if ($length < 15 || $length > 255) {
            throw new InvoiceError('reason must be 15 to 255 characters');
        }
        if ($numberEnd < $numberStart) {
            throw new InvoiceError("numberEnd can't be below numberStart");
        }

        $payload = [
            'series' => $series,
            'number_start' => $numberStart,
            'number_end' => $numberEnd,
            'reason' => $reason,
        ];

        return $this->request('POST', '/invoices/invalidations', json: $payload);
    }

    /**
     * Files a correction letter (CC-e) against an issued document.
     *
     * @return array<string, mixed>
     */
    public function correct(
        string $accessKey,
        DocumentType $documentType,
        string $correction,
    ): array {
        $length = mb_strlen($correction);
        if ($length < 15 || $length > 1000) {
            throw new InvoiceError('correction must be 15 to 1000 characters');
        }

        $payload = [
            'document_type' => $documentType->value,
            'correction' => $correction,
        ];

        return $this->request('POST', "/invoices/{$accessKey}/correction", json: $payload);
    }

    /**
     * Retries a previous invoice submission by its local id.
     *
     * @return array<string, mixed>
     */
    public function reissue(string $invoiceId, ?string $idempotencyKey = null): array
    {
        return $this->request(
            'POST',
            "/invoices/{$invoiceId}/reissue",
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, mixed>|null $query
     * @return array<string, mixed>
     */
    /**
     * The authorizer's own rendering of an authorized document, as raw
     * bytes — the only method that does not return a parsed array.
     *
     * The XML is the legally valid document; this is a convenience, and
     * the authorizer's endpoint for it is unstable, so an ApiError with
     * status 502 means the authorizer is unavailable, not that the
     * invoice is wrong. NFS-e only.
     */
    public function pdf(string $accessKey, DocumentType $documentType): string
    {
        return $this->send(
            'GET',
            "/invoices/{$accessKey}/pdf",
            query: ['document_type' => $documentType->value],
        );
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>|null $query
     *
     * @return array<string, mixed>
     */
    private function request(
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
    private function send(
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
