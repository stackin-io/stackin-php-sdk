<?php

declare(strict_types=1);

namespace Stackin;

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
    ) {
        parent::__construct($apiKey, $baseUrl, $timeout, $country);

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
    public function kinds(?string $country = null): array
    {
        $body = $this->send(
            'GET',
            '/fiscal-references/kinds',
            null,
            ['country' => $country ?? $this->country],
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
            fn (string $path, array $query): array => $this->request('GET', $path, null, $query),
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
    ): array {
        return $this->request(
            'GET',
            '/fiscal-references',
            null,
            self::searchQuery(null, $country ?? $this->country, $term, $limit, $offset),
        );
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
