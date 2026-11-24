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
use Stackin\Errors\ItemAlreadyExistsError;
use Stackin\Errors\ItemInactiveError;
use Stackin\Errors\ItemKindMismatchError;
use Stackin\Errors\ItemNotFoundError;
use Stackin\Errors\ItemTaxRegimeMismatchError;
use Stackin\Errors\ItemTaxRequiredError;
use Stackin\Errors\CompanyNotInScopeError;
use Stackin\Errors\CompanyRequiredError;
use Stackin\Errors\ConnectionFailedError;
use Stackin\Errors\InsufficientPermissionError;
use Stackin\Errors\PaymentRequiredError;
use Stackin\Errors\RateLimitedError;
use stdClass;

/**
 * Where an api key becomes an HTTP call, for every entry point.
 *
 * Invoice, Clients, Items, Webhooks, FiscalReference and Taxpayer all extend it, so one
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

    /**
     * Whether the company can issue right now, and if not, why.
     *
     * ready is true when at least one document type can be issued; when
     * false, missing names the causes: certificate, certificate_expired,
     * certificate_unreadable, address, state_registration (NF-e) or
     * plan. by_document_type breaks it down per nfe/nfse (ready, missing,
     * warnings; an address issue lists its fields), and fix_url is the
     * dashboard screen for the first blocker, null when nothing blocks.
     * Also has_certificate, certificate_expires_at, tax_regime (CRT),
     * state, the default_series per document type and
     * suggested_fiscal_fields, what the CNPJ registry suggests where it
     * differs from what the company holds (never applied). Kept as an
     * array, so a field the API adds arrives without a release.
     * Company::update() fixes what it reports. Ask before a first
     * document instead of failing it.
     *
     * @return array<string, mixed>
     */
    public function readiness(?string $company = null): array
    {
        return $this->request('GET', '/company', company: $company);
    }

    /**
     * The API's pagination query, leaving out what was not passed.
     *
     * @return array<string, string>
     */
    protected static function pageQuery(
        ?int $limit,
        ?int $offset,
        ?string $sortBy,
        ?string $orderBy,
    ): array {
        $query = [];
        if ($limit !== null) {
            $query['limit'] = (string) $limit;
        }
        if ($offset !== null) {
            $query['offset'] = (string) $offset;
        }
        if ($sortBy !== null) {
            $query['sort_by'] = $sortBy;
        }
        if ($orderBy !== null) {
            $query['order_by'] = $orderBy;
        }

        return $query;
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
     * $json is an array, or a stdClass where an empty body must still go
     * out as the JSON object {} rather than [].
     *
     * @param array<string, mixed>|stdClass|null $json
     * @param array<string, string>|null $query
     * @return array<array-key, mixed>
     */
    protected function request(
        string $method,
        string $path,
        array|stdClass|null $json = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
        ?string $company = null,
        ?string $acceptLanguage = null,
    ): array {
        $body = $this->send(
            $method,
            $path,
            $json,
            $query,
            $idempotencyKey,
            $company,
            $acceptLanguage,
        );
        $decoded = $body !== '' ? json_decode($body, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];

        return $decoded['result'] ?? $decoded;
    }

    /**
     * @param array<string, mixed>|stdClass|null $json
     * @param array<string, string>|null $query
     */
    protected function send(
        string $method,
        string $path,
        array|stdClass|null $json = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
        ?string $company = null,
        ?string $acceptLanguage = null,
    ): string {
        return $this->sendWithStatus(
            $method,
            $path,
            $json,
            $query,
            $idempotencyKey,
            $company,
            $acceptLanguage,
        )[1];
    }

    /**
     * Same as send(), keeping the status code for the routes whose 2xx
     * says something (the item upsert: 201 created, 200 updated).
     *
     * @param array<string, mixed>|stdClass|null $json
     * @param array<string, string>|null $query
     * @return array{int, string}
     */
    protected function sendWithStatus(
        string $method,
        string $path,
        array|stdClass|null $json = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
        ?string $company = null,
        ?string $acceptLanguage = null,
    ): array {
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
        if ($acceptLanguage !== null) {
            $headers['Accept-Language'] = $acceptLanguage;
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
            throw self::apiError($status, $detail, $decoded);
        }

        return [$status, $body];
    }

    /**
     * The refusals a caller acts on by name get their own type; every
     * other one stays a plain ApiError with the same detail string.
     *
     * @param array<array-key, mixed> $body
     */
    private static function apiError(int $status, mixed $detail, array $body = []): ApiError
    {
        return self::apiErrorOf($status, $detail)->withFields(
            ...(self::errorFields($detail, $body) + ['body' => $body ?: null]),
        );
    }

    /**
     * code, field, retryable, documentId and sefazRejection from a
     * structured rule detail, an authority "error" block or the body's
     * top level, each null when absent.
     *
     * @param array<array-key, mixed> $body
     * @return array{errorCode: ?string, field: ?string, retryable: ?bool,
     *     documentId: ?string, sefazRejection: ?array<string, mixed>}
     */
    private static function errorFields(mixed $detail, array $body): array
    {
        $block = $body['error'] ?? null;
        $block = is_array($block) ? $block : [];
        $rule = is_array($detail) && !array_is_list($detail) ? $detail : [];

        $code = $block['code'] ?? $rule['code'] ?? $body['code'] ?? null;
        $field = $rule['field'] ?? null;
        $retryable = $block['retryable'] ?? null;
        $documentId = $block['document_id'] ?? null;
        $rejection = $rule['sefaz_rejection'] ?? null;

        return [
            'errorCode' => is_string($code) || is_int($code) ? (string) $code : null,
            'field' => is_string($field) ? $field : null,
            'retryable' => is_bool($retryable) ? $retryable : null,
            'documentId' => is_string($documentId) ? $documentId : null,
            'sefazRejection' => is_array($rejection) ? $rejection : null,
        ];
    }

    private static function apiErrorOf(int $status, mixed $detail): ApiError
    {
        $text = is_string($detail) ? $detail : (string) json_encode($detail);
        if (is_array($detail) && is_string($detail['message'] ?? null)) {
            $text = $detail['message'];
        }
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

        $itemId = $detail['item_id'] ?? '';
        $itemId = is_string($itemId) ? $itemId : '';
        if ($status === 409 && $code === 'ITEM_EXISTS') {
            $field = $detail['field'] ?? null;

            return new ItemAlreadyExistsError(
                $status,
                $text,
                $itemId,
                is_string($field) ? $field : null,
            );
        }
        if ($status === 404 && $code === 'ITEM_NOT_FOUND') {
            return new ItemNotFoundError($status, $text, $itemId);
        }
        if ($status === 422 && $code === 'ITEM_INACTIVE') {
            return new ItemInactiveError($status, $text, $itemId);
        }
        if ($status === 422 && $code === 'ITEM_KIND_MISMATCH') {
            $index = $detail['index'] ?? null;

            return new ItemKindMismatchError(
                $status,
                $text,
                $itemId,
                is_int($index) ? $index : null,
            );
        }
        if ($status === 422 && $code === 'ITEM_TAX_REGIME_MISMATCH') {
            $index = $detail['index'] ?? null;

            return new ItemTaxRegimeMismatchError(
                $status,
                $text,
                $itemId,
                is_int($index) ? $index : null,
            );
        }
        if ($status === 422 && $code === 'ITEM_TAX_REQUIRED') {
            $index = $detail['index'] ?? null;

            return new ItemTaxRequiredError(
                $status,
                $text,
                $itemId,
                is_int($index) ? $index : null,
            );
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

        $error = new RateLimitedError(
            429,
            $text,
            ctype_digit($seconds) ? (int) $seconds : null,
            is_string($retryAt) ? self::moment($retryAt) : null,
            is_string($code) ? $code : null,
        );

        return $error->withFields(
            ...(self::errorFields($detail, $body) + ['body' => $body ?: null]),
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
