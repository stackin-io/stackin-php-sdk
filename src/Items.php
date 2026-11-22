<?php

declare(strict_types=1);

namespace Stackin;

use Stackin\Br\Product;
use stdClass;

/**
 * The company's catalog of products and services: what it sells, kept
 * once and named by Product::$itemId on an issuance line.
 *
 * It is company data, like everything on Invoice, so every method takes a
 * $company for one call. Reading needs invoice:read; saving, editing and
 * deactivating need invoice:issue. An item is a `product` (NF-e) or a
 * `service` (NFS-e); a fiscal field of the other kind is refused.
 *
 * The rules on the fields are the API's: a refusal comes back as an
 * ApiError 422. Nothing fiscal is required to save an item; an NF-e still
 * refuses a line without ncm and cfop when it is issued.
 */
final class Items extends Client
{
    /**
     * A page of the company's items, by description unless $sortBy says
     * otherwise.
     *
     * $kind is product or service. $search matches part of the
     * description, code or external reference (digits also match ncm and
     * gtin). $source is manual, import, nuvemshop or invoice. The API
     * lists the active items when $isActive is null; false lists the
     * deactivated ones. $sortBy takes description, code, unit_price,
     * last_used_at, created_at or uses_count. The envelope is returned as
     * the API sends it.
     *
     * @return array<string, mixed>
     */
    public function list(
        ?string $kind = null,
        ?string $search = null,
        ?string $source = null,
        ?bool $isActive = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $sortBy = null,
        ?string $orderBy = null,
        ?string $company = null,
    ): array {
        $query = [];
        if ($kind !== null) {
            $query['kind'] = $kind;
        }
        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }
        if ($source !== null) {
            $query['source'] = $source;
        }
        if ($isActive !== null) {
            $query['is_active'] = $isActive ? 'true' : 'false';
        }
        $query += self::pageQuery($limit, $offset, $sortBy, $orderBy);

        return $this->request('GET', '/items', query: $query, company: $company);
    }

    /**
     * One item, deactivated or not. Another company's is a 404.
     *
     * @return array<string, mixed>
     */
    public function get(string $itemId, ?string $company = null): array
    {
        $escaped = FiscalReference::segment($itemId);

        return $this->request('GET', "/items/{$escaped}", company: $company);
    }

    /**
     * Saves an item, or updates the company's existing one: the same
     * $externalReference, or the same $code when none is sent. Sending the
     * same arguments twice never makes two items. Only what is passed
     * (not null) is sent, so an update leaves the other fields alone; the
     * item keeps its source and its active state. $source (manual, import,
     * nuvemshop or invoice; manual when left out) is used only when the
     * item is created.
     *
     * ItemSaved::$created tells the 201 (new) from the 200 (updated). A
     * $code that belongs to another item is an ItemAlreadyExistsError.
     * $issRetained is for a service; $origin needs $taxSituation.
     */
    public function upsert(
        string $kind,
        string $description,
        ?string $code = null,
        float|string|null $unitPrice = null,
        ?string $unit = null,
        ?string $source = null,
        ?string $externalReference = null,
        ?string $notes = null,
        ?string $ncm = null,
        ?string $cfop = null,
        ?string $taxSituation = null,
        ?string $origin = null,
        ?string $cest = null,
        ?string $gtin = null,
        ?string $serviceCode = null,
        ?bool $issRetained = null,
        ?string $company = null,
    ): ItemSaved {
        $fields = [
            'kind' => $kind,
            'description' => $description,
            'code' => $code,
            'unit_price' => $unitPrice === null ? null : Product::priceString($unitPrice),
            'unit' => $unit,
            'source' => $source,
            'external_reference' => $externalReference,
            'notes' => $notes,
            'ncm' => $ncm,
            'cfop' => $cfop,
            'tax_situation' => $taxSituation,
            'origin' => $origin,
            'cest' => $cest,
            'gtin' => $gtin,
            'service_code' => $serviceCode,
            'iss_retained' => $issRetained,
        ];
        $payload = array_filter($fields, static fn (mixed $value): bool => $value !== null);

        [$status, $body] = $this->sendWithStatus('POST', '/items', $payload, company: $company);
        $decoded = $body !== '' ? json_decode($body, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];
        $data = $decoded['result'] ?? $decoded;

        return new ItemSaved(is_array($data) ? $data : [], $status === 201);
    }

    /**
     * Same request as upsert(), answering the item alone. Kept for parity
     * with Clients::create(); it does not fail on an existing item, it
     * updates it. Use upsert() when it matters which happened.
     *
     * @return array<array-key, mixed>
     */
    public function create(
        string $kind,
        string $description,
        ?string $code = null,
        float|string|null $unitPrice = null,
        ?string $unit = null,
        ?string $source = null,
        ?string $externalReference = null,
        ?string $notes = null,
        ?string $ncm = null,
        ?string $cfop = null,
        ?string $taxSituation = null,
        ?string $origin = null,
        ?string $cest = null,
        ?string $gtin = null,
        ?string $serviceCode = null,
        ?bool $issRetained = null,
        ?string $company = null,
    ): array {
        return $this->upsert(
            $kind,
            $description,
            $code,
            $unitPrice,
            $unit,
            $source,
            $externalReference,
            $notes,
            $ncm,
            $cfop,
            $taxSituation,
            $origin,
            $cest,
            $gtin,
            $serviceCode,
            $issRetained,
            $company,
        )->item;
    }

    /**
     * Changes only the fields in $changes, keyed by their API names: kind,
     * description, code, unit_price, unit, external_reference, notes, ncm,
     * cfop, tax_situation, origin, cest, gtin, service_code, iss_retained,
     * is_active. A null value clears the field.
     *
     * The API checks the item as a whole, so turning a product into a
     * service while it still has an ncm is refused (422); taking another
     * item's code or external_reference is an ItemAlreadyExistsError.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function update(string $itemId, array $changes, ?string $company = null): array
    {
        $escaped = FiscalReference::segment($itemId);
        $price = $changes['unit_price'] ?? null;
        if (is_int($price) || is_float($price) || is_string($price)) {
            $changes['unit_price'] = Product::priceString(is_string($price) ? $price : (float) $price);
        }
        $body = $changes === [] ? new stdClass() : $changes;

        return $this->request('PATCH', "/items/{$escaped}", json: $body, company: $company);
    }

    /**
     * Deactivates an item: kept, left out of list() by default, and
     * refused on issue until reactivate() brings it back. It still holds
     * its code and external reference.
     */
    public function deactivate(string $itemId, ?string $company = null): void
    {
        $escaped = FiscalReference::segment($itemId);

        $this->send('DELETE', "/items/{$escaped}", company: $company);
    }

    /**
     * Brings a deactivated item back, and returns it.
     *
     * @return array<string, mixed>
     */
    public function reactivate(string $itemId, ?string $company = null): array
    {
        return $this->update($itemId, ['is_active' => true], $company);
    }
}
