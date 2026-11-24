<?php

declare(strict_types=1);

namespace Stackin;

use DateTimeImmutable;
use Stackin\Errors\InvoiceError;

/**
 * Reads the published classification tables — CFOP, NCM, CEST and the
 * rest. None of it is the company's own data and none of it is
 * writable.
 *
 * The eight named accessors are ergonomics; kind() is the contract,
 * and it reaches a classification published after this release with no
 * release of this package at all.
 */
final class FiscalReference extends Client
{
    /**
     * The classifications with a named accessor. Not the whole truth
     * and not meant to be — ask kinds() for what the API actually has.
     *
     * @var list<string>
     */
    public const KINDS = [
        'cfop',
        'ncm',
        'cest',
        'cst',
        'csosn',
        'iss_service',
        'icms_fuel',
        'ibs_cbs_class',
    ];

    public readonly Kind $cfop;
    public readonly Kind $ncm;
    public readonly Kind $cest;
    public readonly Kind $cst;
    public readonly Kind $csosn;
    public readonly Kind $issService;
    public readonly Kind $icmsFuel;
    public readonly Kind $ibsCbsClass;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        string $country = 'BR',
        ?string $company = null,
    ) {
        parent::__construct($apiKey, $baseUrl, $timeout, $country, $company);

        $this->cfop = $this->kind('cfop');
        $this->ncm = $this->kind('ncm');
        $this->cest = $this->kind('cest');
        $this->cst = $this->kind('cst');
        $this->csosn = $this->kind('csosn');
        $this->issService = $this->kind('iss_service');
        $this->icmsFuel = $this->kind('icms_fuel');
        $this->ibsCbsClass = $this->kind('ibs_cbs_class');
    }

    /**
     * Which classifications this country has rows for.
     *
     * The only honest answer to "what else is there" — a hard-coded
     * list goes stale the next time the ETL grows one.
     *
     * @return list<string>
     */
    public function kinds(?string $country = null, ?string $company = null): array
    {
        $body = $this->send(
            'GET',
            '/fiscal-references/kinds',
            null,
            ['country' => $country ?? $this->country],
            company: $company,
        );

        $decoded = $body !== '' ? json_decode($body, true) : [];
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new InvoiceError('unexpected response shape: expected a list');
        }

        /** @var list<string> $decoded */
        return $decoded;
    }

    /**
     * Any classification by name, including one with no accessor.
     */
    public function kind(string $name, ?string $country = null): Kind
    {
        return new Kind(
            fn (string $path, array $query, ?string $company = null): array => $this->request(
                'GET',
                $path,
                null,
                $query,
                company: $company,
            ),
            $name,
            $country ?? $this->country,
        );
    }

    /**
     * Every classification at once, which no accessor can express.
     *
     * The most expensive call the endpoint accepts: the whole
     * country's tables, not one of them.
     *
     * @return array<string, mixed>
     */
    public function search(
        ?string $term = null,
        ?string $country = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $company = null,
    ): array {
        return $this->request(
            'GET',
            '/fiscal-references',
            null,
            self::searchQuery(null, $country ?? $this->country, $term, $limit, $offset),
            company: $company,
        );
    }

    /**
     * Municipalities whose name starts with $name, with their code.
     *
     * Each row is {country, code, name, state}; for Brazil code is the
     * 7-digit IBGE code an address's cityCode takes. Issuing resolves it
     * from city and state by itself, so this is for the name that
     * matches more than one. $state narrows to one UF; the API answers
     * at most 20 rows.
     *
     * @return list<array<string, mixed>>
     */
    public function cities(
        string $name,
        ?string $state = null,
        ?string $country = null,
        ?string $company = null,
    ): array {
        $length = mb_strlen($name);
        if ($length < 2 || $length > 100) {
            throw new InvoiceError('name must be 2 to 100 characters');
        }
        if ($state !== null && mb_strlen($state) !== 2) {
            throw new InvoiceError('state must be a two-letter code');
        }

        $query = ['name' => $name, 'country' => $country ?? $this->country];
        if ($state !== null) {
            $query['state'] = strtoupper($state);
        }

        $rows = $this->request('GET', '/cities', query: $query, company: $company);
        if (!array_is_list($rows)) {
            throw new InvoiceError('unexpected response shape: expected a list');
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * Suggests the NBS codes NFS-e Anexo VIII correlates with a service code
     * (GET /service-codes/{service_code}/nbs), in any form the line's
     * serviceCode takes: '1.07', '01.07.00' or '010700'. Only its LC 116
     * subitem decides the answer.
     *
     * Each row also carries the operationIndicator (cIndOp) and
     * ibsCbsClassification (cClassTrib) the annex pairs with that NBS. They
     * are suggestions only: nothing is written into a document unless they
     * are sent as the line's nbs and its ibsCbs. A code the route cannot read
     * is an ApiError 422.
     */
    public function nbsSuggestions(string $serviceCode, ?string $company = null): NbsSuggestions
    {
        if (mb_strlen($serviceCode) > 12) {
            throw new InvoiceError('serviceCode must be at most 12 characters');
        }

        return NbsSuggestions::fromArray($this->request(
            'GET',
            '/service-codes/' . self::segment($serviceCode) . '/nbs',
            company: $company,
        ));
    }

    /**
     * Simulates the IBS and CBS of the items, with no document issued or
     * stored (POST /ibs-cbs/calculate).
     *
     * $items is a list of IbsCbsCalculationItem, or arrays with the same
     * snake_case keys: cst, classification, amount and/or base, the three
     * rates and the two reductions. $operationDate is YYYY-MM-DD.
     * $documentType (nfe, nfce or nfse) only picks the model the cClassTrib
     * is checked for. $rates are the defaults for items that carry none:
     * left out in 2026 the official 2026 rates apply, in any other year
     * the API refuses with 422 IBS_CBS_RATES_REQUIRED. 1 to 990 items;
     * the rest is checked by the API.
     *
     * @param list<IbsCbsCalculationItem|array<string, mixed>> $items
     * @param IbsCbsRates|array<string, mixed>|null $rates
     */
    public function calculateIbsCbs(
        array $items,
        string $operationDate,
        string $documentType = 'nfe',
        IbsCbsRates|array|null $rates = null,
        ?string $company = null,
    ): IbsCbsCalculation {
        if ($items === [] || count($items) > 990) {
            throw new InvoiceError('items must have 1 to 990 entries');
        }
        if (!in_array($documentType, ['nfe', 'nfce', 'nfse'], true)) {
            throw new InvoiceError('documentType must be nfe, nfce or nfse');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $operationDate);
        if ($date === false || $date->format('Y-m-d') !== $operationDate) {
            throw new InvoiceError('operationDate must be a YYYY-MM-DD date');
        }

        $body = [
            'document_type' => $documentType,
            'operation_date' => $operationDate,
            'items' => array_map(
                static fn (IbsCbsCalculationItem|array $item): array => (
                    $item instanceof IbsCbsCalculationItem
                        ? $item
                        : IbsCbsCalculationItem::fromArray($item)
                )->toArray(),
                array_values($items),
            ),
        ];
        if ($rates !== null) {
            $rates = $rates instanceof IbsCbsRates ? $rates : IbsCbsRates::fromArray($rates);
            if ($rates->toArray() !== []) {
                $body['rates'] = $rates->toArray();
            }
        }

        return IbsCbsCalculation::fromArray(
            $this->request('POST', '/ibs-cbs/calculate', json: $body, company: $company),
        );
    }

    /**
     * One path segment, escaped, refusing the ones that would leave it.
     *
     * rawurlencode leaves '.' alone — RFC 3986 calls it unreserved — so
     * the dot segments have to be refused rather than escaped, or the
     * HTTP client collapses '..' into a different endpoint.
     *
     * @internal Shared with Kind.
     */
    public static function segment(string $value): string
    {
        if ($value === '' || $value === '.' || $value === '..') {
            throw new InvoiceError("'{$value}' is not a usable path segment");
        }

        return rawurlencode($value);
    }

    /**
     * @internal Shared with Kind::search().
     * @return array<string, string>
     */
    public static function searchQuery(
        ?string $kind,
        string $country,
        ?string $term,
        ?int $limit,
        ?int $offset,
    ): array {
        $query = ['country' => $country];
        if ($kind !== null) {
            $query['kind'] = $kind;
        }
        if ($term !== null && $term !== '') {
            $query['search'] = $term;
        }
        if ($limit !== null) {
            $query['limit'] = (string) $limit;
        }
        if ($offset !== null) {
            $query['offset'] = (string) $offset;
        }

        return $query;
    }
}
