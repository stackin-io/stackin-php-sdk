<?php

declare(strict_types=1);

namespace Stackin;

/**
 * The company's saved clients: the recipients it issues to again.
 *
 * A successful issuance saves a new recipient on its own, and
 * Invoice::issue() takes a $clientId to issue to a saved one. This is
 * company data, like everything on Invoice, so every method takes a
 * $company for one call. Reading needs invoice:read; creating, editing
 * and deactivating need invoice:issue.
 *
 * The rules on a client's fields are the issuance's own (a CPF/CNPJ
 * that checks out, a buyer abroad identified by foreign_id) and they
 * are the API's: a refusal comes back as an ApiError 422.
 */
final class Clients extends Client
{
    /**
     * A page of the company's clients, by name unless $sortBy says
     * otherwise.
     *
     * $search matches part of the name, the tax id (masked or not) or
     * the foreign_id. $source is manual, issued or issuance. The API
     * lists the active clients when $isActive is null; false lists the
     * deactivated ones. $sortBy takes name, last_used_at, created_at or
     * uses_count.
     *
     * @return array<string, mixed>
     */
    public function list(
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
        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }
        if ($source !== null) {
            $query['source'] = $source;
        }
        if ($isActive !== null) {
            $query['is_active'] = $isActive ? 'true' : 'false';
        }
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

        return $this->request('GET', '/clients', query: $query, company: $company);
    }

    /**
     * One client, deactivated or not. Another company's is a 404.
     *
     * @return array<string, mixed>
     */
    public function get(string $clientId, ?string $company = null): array
    {
        $escaped = FiscalReference::segment($clientId);

        return $this->request('GET', "/clients/{$escaped}", company: $company);
    }

    /**
     * Saves a client by hand. A $taxId or $foreignId the company already
     * holds is a ClientAlreadyExistsError, whose clientId names that
     * client. Each argument left null is left out of the request.
     *
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        ?string $taxId = null,
        ?string $foreignId = null,
        ?string $stateRegistration = null,
        ?string $municipalRegistration = null,
        ?string $email = null,
        ?string $phone = null,
        ?Address $address = null,
        ?string $notes = null,
        ?string $company = null,
    ): array {
        $fields = [
            'name' => $name,
            'tax_id' => $taxId,
            'foreign_id' => $foreignId,
            'state_registration' => $stateRegistration,
            'municipal_registration' => $municipalRegistration,
            'email' => $email,
            'phone' => $phone,
            'address' => $address?->toArray(),
            'notes' => $notes,
        ];
        $payload = array_filter($fields, static fn (mixed $value): bool => $value !== null);

        return $this->request('POST', '/clients', json: $payload, company: $company);
    }

    /**
     * Changes only the fields in $changes, keyed by their API names:
     * name, tax_id, foreign_id, state_registration,
     * municipal_registration, email, phone, address, notes, is_active.
     * A null value clears the field; address (an Address or an array)
     * replaces the whole address.
     *
     * The API checks the client as a whole, so clearing a Brazilian
     * client's tax_id is refused like a create without one; taking
     * another client's tax_id is a ClientAlreadyExistsError.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function update(string $clientId, array $changes, ?string $company = null): array
    {
        $escaped = FiscalReference::segment($clientId);
        if (($changes['address'] ?? null) instanceof Address) {
            $changes['address'] = $changes['address']->toArray();
        }

        return $this->request('PATCH', "/clients/{$escaped}", json: $changes, company: $company);
    }

    /**
     * Deactivates a client: kept, left out of list() by default, and
     * refused on issue until reactivate() brings it back.
     */
    public function deactivate(string $clientId, ?string $company = null): void
    {
        $escaped = FiscalReference::segment($clientId);

        $this->send('DELETE', "/clients/{$escaped}", company: $company);
    }

    /**
     * Brings a deactivated client back, and returns it.
     *
     * @return array<string, mixed>
     */
    public function reactivate(string $clientId, ?string $company = null): array
    {
        return $this->update($clientId, ['is_active' => true], $company);
    }
}
