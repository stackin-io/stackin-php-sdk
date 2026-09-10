<?php

declare(strict_types=1);

namespace Stackin;

/**
 * Reads the taxpayer registry, one tax id at a time.
 *
 * One method, and it stays one method. The registry names and
 * addresses real people and companies: an exact lookup answers the
 * question an issuer has — who is this CNPJ I am about to invoice —
 * and answers nothing else. A prefix or wildcard search over the same
 * table is a bulk export wearing the costume of a query. Do not add
 * one, not for parity with FiscalReference, not "just by name", not
 * "just within one state".
 */
final class Taxpayer extends Client
{
    /**
     * One taxpayer, by exact tax id.
     *
     * A 404 arrives as an ApiError and does not mean the taxpayer does
     * not exist: the registry reloads monthly, so a company registered
     * in the last few weeks is simply not in it yet. Never build a
     * validation rule on top of it.
     *
     * @return array<string, mixed>
     */
    public function get(string $taxId, ?string $country = null): array
    {
        $escaped = FiscalReference::segment($taxId);

        return $this->request(
            'GET',
            "/taxpayers/{$escaped}",
            null,
            ['country' => $country ?? $this->country],
        );
    }
}
