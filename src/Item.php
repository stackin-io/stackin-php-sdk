<?php

declare(strict_types=1);

namespace Stackin;

use DateTimeImmutable;

/**
 * One entry of the company's catalog of products and services, as the
 * Items resource returns it.
 *
 * kind is `product` (NF-e) or `service` (NFS-e) and decides which fiscal
 * fields the item may carry: ncm, cfop, taxSituation, origin, cest and
 * gtin belong to a product; serviceCode and issRetained to a service. The
 * rest are null on the other kind. source is manual, import, nuvemshop or
 * invoice. Fields the API adds later are ignored rather than refused.
 */
final class Item
{
    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $kind,
        public readonly string $description,
        public readonly ?string $code,
        public readonly ?float $unitPrice,
        public readonly ?string $unit,
        public readonly string $source,
        public readonly ?string $externalReference,
        public readonly ?string $notes,
        public readonly ?string $ncm,
        public readonly ?string $cfop,
        public readonly ?string $taxSituation,
        public readonly ?string $origin,
        public readonly ?string $cest,
        public readonly ?string $gtin,
        public readonly ?string $serviceCode,
        public readonly ?bool $issRetained,
        public readonly bool $isActive,
        public readonly int $usesCount,
        public readonly ?DateTimeImmutable $lastUsedAt,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            companyId: (string) ($data['company_id'] ?? ''),
            kind: (string) ($data['kind'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            code: self::text($data['code'] ?? null),
            unitPrice: isset($data['unit_price']) && is_numeric($data['unit_price'])
                ? (float) $data['unit_price']
                : null,
            unit: self::text($data['unit'] ?? null),
            source: (string) ($data['source'] ?? ''),
            externalReference: self::text($data['external_reference'] ?? null),
            notes: self::text($data['notes'] ?? null),
            ncm: self::text($data['ncm'] ?? null),
            cfop: self::text($data['cfop'] ?? null),
            taxSituation: self::text($data['tax_situation'] ?? null),
            origin: self::text($data['origin'] ?? null),
            cest: self::text($data['cest'] ?? null),
            gtin: self::text($data['gtin'] ?? null),
            serviceCode: self::text($data['service_code'] ?? null),
            issRetained: isset($data['iss_retained']) ? (bool) $data['iss_retained'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            usesCount: (int) ($data['uses_count'] ?? 0),
            lastUsedAt: self::moment($data['last_used_at'] ?? null),
            createdAt: self::moment($data['created_at'] ?? null),
            updatedAt: self::moment($data['updated_at'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function moment(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
