<?php

declare(strict_types=1);

namespace Stackin;

use DateTimeInterface;
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
final class Invoice extends Client
{
    /**
     * Rejects a missing or partial buyer address before the network call —
     * the SEFAZ rejects a partial enderDest (274/726/696/695). cityCode is
     * not required: the API resolves it from city and state, and answers
     * CITY_NOT_FOUND (422) when it cannot. A buyer abroad (state EX) has
     * no zipCode either.
     */
    private static function validateNfeAddress(?Address $address): void
    {
        if ($address === null) {
            throw new InvoiceError('recipientAddress is required for NFE');
        }

        self::requireAddressFields($address, 'NFE', $address->isAbroad());
    }

    /**
     * On an NFS-e with any retained item (ISSQN retained by the tomador) the
     * city authorizer refuses the document without the tomador's address
     * (E0237), so it is required there — every field but cityCode.
     *
     * @param Product[] $items
     */
    private static function validateNfseRetainedAddress(array $items, ?Address $address): void
    {
        $retained = array_filter($items, static fn (Product $item): bool => $item->taxRetained);
        if ($retained === []) {
            return;
        }
        if ($address === null) {
            throw new InvoiceError(
                'recipient_address is required on an NFS-e when any item has '
                . 'tax_retained=true (ISSQN retained by the tomador)',
            );
        }

        self::requireAddressFields($address, 'NFSE when any item has tax_retained=true');
    }

    private static function requireAddressFields(
        Address $address,
        string $context,
        bool $abroad = false,
    ): void {
        $fields = [
            'street' => $address->street,
            'number' => $address->number,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
        ];
        if (!$abroad) {
            $fields['zipCode'] = $address->zipCode;
        }

        $missing = array_keys(array_filter(
            $fields,
            static fn (?string $value): bool => $value === null || $value === '',
        ));

        if ($missing !== []) {
            throw new InvoiceError(
                "recipientAddress is missing required fields for {$context}: "
                . implode(', ', $missing),
            );
        }
    }

    /**
     * Typographic characters above U+00FF that the API converts before
     * checking (quotes, dashes, ellipsis, bullet, euro, trademark), so the
     * SDK must not refuse them.
     */
    private const NFE_CONVERTED_CHARS = [
        0x2018, 0x2019, 0x201A, 0x201C, 0x201D, 0x201E,
        0x2013, 0x2014, 0x2212, 0x2026, 0x2022, 0x20AC, 0x2122,
    ];

    /**
     * Rejects the first free-text field holding a character the NF-e cannot
     * carry: above U+00FF and not one of the characters the API converts.
     * Anything up to U+00FF (tab, newline and CR included) is let through —
     * the API folds whitespace runs itself.
     *
     * @param Product[] $items
     */
    private static function validateNfeLatin1(
        ?string $clientName,
        array $items,
        ?Address $address,
    ): void {
        $fields = ['client_name' => $clientName];
        foreach ($items as $index => $item) {
            $fields["items[{$index}].description"] = $item->description;
            $fields["items[{$index}].unit"] = $item->unit;
            $fields["items[{$index}].purchase_order"] = $item->purchaseOrder;
            $fields["items[{$index}].purchase_order_item"] = $item->purchaseOrderItem;
            $fields["items[{$index}].observations"] = $item->observations;
            $fields["items[{$index}].fuel.anp_description"] = $item->fuel?->anpDescription;
        }
        foreach ($address?->toArray() ?? [] as $key => $value) {
            $fields["recipient_address.{$key}"] = $value;
        }

        foreach ($fields as $path => $value) {
            if ($value === null) {
                continue;
            }
            foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
                $code = mb_ord($char, 'UTF-8');
                if ($code > 0xFF && !in_array($code, self::NFE_CONVERTED_CHARS, true)) {
                    throw new InvoiceError(
                        "{$path} contains a character outside ISO-8859-1 (latin1), "
                        . "which NF-e does not accept: '{$char}'",
                    );
                }
            }
        }
    }

    /**
     * Money as the API takes it: a decimal string, never a binary float.
     */
    private static function decimalString(?float $value): ?string
    {
        return $value === null ? null : rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    /**
     * Issues a fiscal document.
     *
     * Pass $idempotencyKey to make a retry safe: the same key with the same
     * body replays the first response instead of issuing a second document.
     *
     * $externalReference is the caller's own id for the document, an order
     * number say (up to 255 characters): stored as sent and returned on every
     * read and webhook. It is left out of the request when null.
     *
     * Every argument after it is optional too and left out of the request
     * when null, so a call that does not pass them sends the same body:
     *
     * - $discount: a discount on the whole document, in BRL, spread by the
     *   API over the lines.
     * - $issuedAt: the emission moment, sent as RFC 3339 with its offset.
     *   NF-e: at most 30 days back and 5 minutes ahead; NFS-e: never ahead.
     * - NF-e only: $purpose (normal by default), $direction (out by
     *   default) and $referencedKeys, the 44-character access keys of the
     *   documents this one refers to — required for a complementary note
     *   and, but for a few CFOPs, for a return.
     * - NF-e to a buyer abroad (recipientAddress state EX): $taxId is left
     *   out (null), $foreignId identifies the buyer and $export says where
     *   the goods leave Brazil.
     *
     * $taxId is required everywhere else. The fiscal rules between these
     * fields belong to the API, which answers them by name.
     *
     * $clientId names a saved client of the company (see Clients): its
     * name, tax id, foreign id and address fill whichever of $clientName,
     * $taxId, $foreignId and $recipientAddress are null, and the ones
     * given win. Those four are then not required here: the API checks
     * the merged request. After a successful issuance a new recipient is
     * saved as a client; $updateClient true also rewrites an existing one
     * with this request's recipient data. Both are left out of the
     * request when unset.
     *
     * $contingency true sends an NF-e through the UF's contingency
     * authorizer (SVC-AN/SVC-RS, tpEmis 6/7) for when its own is down.
     * Left false, it is not sent.
     *
     * @param Product[] $items
     * @param string[]|null $referencedKeys
     * @return array<string, mixed>
     */
    public function issue(
        DocumentType $documentType,
        ?string $clientName,
        ?string $taxId,
        array $items,
        ?Address $recipientAddress = null,
        ?string $series = null,
        ?string $number = null,
        ?string $idempotencyKey = null,
        ?string $externalReference = null,
        ?float $discount = null,
        ?DateTimeInterface $issuedAt = null,
        ?Purpose $purpose = null,
        ?Direction $direction = null,
        ?array $referencedKeys = null,
        ?string $foreignId = null,
        ?Export $export = null,
        ?string $company = null,
        ?string $clientId = null,
        bool $updateClient = false,
        bool $contingency = false,
    ): array {
        if ($items === []) {
            throw new InvoiceError("items can't be empty");
        }

        $fromClient = $clientId !== null;
        if ($clientName === null && !$fromClient) {
            throw new InvoiceError('clientName is required unless clientId is given');
        }

        if ($documentType === DocumentType::NFE) {
            foreach ($items as $index => $item) {
                if (!$item->ncm) {
                    throw new InvoiceError("items[{$index}].ncm is required for NFE");
                }
                if (!$item->cfop) {
                    throw new InvoiceError("items[{$index}].cfop is required for NFE");
                }
                if (mb_strlen($item->description) > 120) {
                    throw new InvoiceError(
                        "items[{$index}].description exceeds 120 characters (NF-e xProd limit)",
                    );
                }
            }
            if ($recipientAddress !== null || !$fromClient) {
                self::validateNfeAddress($recipientAddress);
            }
            self::validateNfeLatin1($clientName, $items, $recipientAddress);
        }

        $abroad = $documentType === DocumentType::NFE && $recipientAddress?->isAbroad();
        if ($taxId === null && !$abroad && !$fromClient) {
            throw new InvoiceError(
                'taxId is required, except for an NF-e to a buyer abroad '
                . '(recipientAddress state EX)',
            );
        }

        if ($documentType === DocumentType::NFSE && ($recipientAddress !== null || !$fromClient)) {
            self::validateNfseRetainedAddress($items, $recipientAddress);
        }

        $payload = ['document_type' => $documentType->value];
        if ($clientId !== null) {
            $payload['client_id'] = $clientId;
        }
        if ($clientName !== null) {
            $payload['client_name'] = $clientName;
        }
        if ($taxId !== null) {
            $payload['tax_id'] = $taxId;
        }
        $payload['items'] = array_map(
            static fn (Product $item): array => $item->toArray(),
            $items,
        );
        if ($recipientAddress !== null) {
            $payload['recipient_address'] = $recipientAddress->toArray();
        }
        if ($series !== null) {
            $payload['series'] = $series;
        }
        if ($number !== null) {
            $payload['number'] = $number;
        }
        if ($externalReference !== null) {
            $payload['external_reference'] = $externalReference;
        }
        $optional = [
            'discount' => self::decimalString($discount),
            'issued_at' => $issuedAt?->format(DateTimeInterface::RFC3339),
            'purpose' => $purpose?->value,
            'direction' => $direction?->value,
            'referenced_keys' => $referencedKeys === null ? null : array_values($referencedKeys),
            'foreign_id' => $foreignId,
            'export' => $export?->toArray(),
            'update_client' => $updateClient ? true : null,
            'contingency' => $contingency ? true : null,
        ];
        foreach ($optional as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $this->request(
            'POST',
            '/invoices',
            json: $payload,
            idempotencyKey: $idempotencyKey,
            company: $company,
        );
    }

    /**
     * Consults a fiscal document by its access key.
     *
     * @return array<string, mixed>
     */
    public function consult(
        string $accessKey,
        DocumentType $documentType,
        ?string $company = null,
    ): array {
        return $this->request(
            'GET',
            "/invoices/{$accessKey}",
            query: ['document_type' => $documentType->value],
            company: $company,
        );
    }

    /**
     * Cancels a fiscal document by its access key.
     *
     * Pass $idempotencyKey to make a retry safe: the same key with the same
     * payload replays the first answer instead of cancelling twice.
     *
     * @return array<string, mixed>
     */
    public function cancel(
        string $accessKey,
        DocumentType $documentType,
        string $reason,
        ?string $idempotencyKey = null,
        ?string $company = null,
    ): array {
        $payload = [
            'document_type' => $documentType->value,
            'reason' => $reason,
        ];

        return $this->request(
            'POST',
            "/invoices/{$accessKey}/cancel",
            json: $payload,
            idempotencyKey: $idempotencyKey,
            company: $company,
        );
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
        ?string $company = null,
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

        return $this->request(
            'POST',
            '/invoices/invalidations',
            json: $payload,
            company: $company,
        );
    }

    /**
     * Files a correction letter (CC-e) against an issued document.
     *
     * NF-e only: a key that is not an NF-e access key (44 characters;
     * the issuer CNPJ in it may be alphanumeric) is refused locally with
     * InvoiceError — an NFS-e has no correction letter, so cancel and
     * reissue it instead. An NF-e key sent with
     * DocumentType::NFSE, or one the API does not hold, still comes back
     * from the API as an ApiError with status 409.
     *
     * @return array<string, mixed>
     */
    public function correct(
        string $accessKey,
        DocumentType $documentType,
        string $correction,
        ?string $company = null,
    ): array {
        if (preg_match('/^[0-9]{6}[0-9A-Z]{12}[0-9]{26}$/D', $accessKey) !== 1) {
            throw new InvoiceError(
                'correct is NFe only. NFSe has no correction letter — cancel and reissue instead.',
            );
        }
        $length = mb_strlen($correction);
        if ($length < 15 || $length > 1000) {
            throw new InvoiceError('correction must be 15 to 1000 characters');
        }

        $payload = [
            'document_type' => $documentType->value,
            'correction' => $correction,
        ];

        return $this->request(
            'POST',
            "/invoices/{$accessKey}/correction",
            json: $payload,
            company: $company,
        );
    }

    /**
     * Retries a previous invoice submission by its local id.
     *
     * @return array<string, mixed>
     */
    public function reissue(
        string $invoiceId,
        ?string $idempotencyKey = null,
        ?string $company = null,
    ): array {
        return $this->request(
            'POST',
            "/invoices/{$invoiceId}/reissue",
            idempotencyKey: $idempotencyKey,
            company: $company,
        );
    }

    /**
     * Documents other companies issued against this one.
     *
     * Reads what the API already collected; it does not call the
     * authorizer. Collecting runs on a schedule there (every six hours)
     * and on demand through syncReceived(). The first 50 received a
     * month are included in every plan, then R$ 0,05 each is taken from
     * the account credit; usage()->receivedFree says whether this
     * account is exempt.
     *
     * @return array<string, mixed>
     */
    public function received(
        ?int $limit = null,
        ?int $offset = null,
        ?string $company = null,
    ): array {
        $query = [];
        if ($limit !== null) {
            $query['limit'] = (string) $limit;
        }
        if ($offset !== null) {
            $query['offset'] = (string) $offset;
        }

        return $this->request('GET', '/received-invoices', query: $query, company: $company);
    }

    /**
     * Collect received documents from the SEFAZ now, instead of waiting
     * for the six-hourly schedule.
     *
     * After a read that finds nothing more the SEFAZ imposes a one-hour
     * wait; a sync inside it throws RateLimitedError with code
     * DISTRIBUTION_COOLDOWN and retryAt set to when it ends.
     */
    public function syncReceived(?string $company = null): ReceivedSync
    {
        return ReceivedSync::fromArray(
            $this->request('POST', '/received-invoices/sync', company: $company)
        );
    }

    /**
     * What this company issued, newest first.
     *
     * The counterpart of received(): that one lists what was issued
     * against the company, this one what the company issued.
     *
     * $externalReference matches your own id exactly. $fields 'summary'
     * returns a lighter item per invoice.
     *
     * @return array<string, mixed>
     */
    public function history(
        ?DocumentType $documentType = null,
        ?string $status = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $sortBy = null,
        ?string $orderBy = null,
        ?string $company = null,
        ?string $externalReference = null,
        ?string $fields = null,
    ): array {
        $query = [];
        if ($documentType !== null) {
            $query['document_type'] = $documentType->value;
        }
        if ($status !== null) {
            $query['status'] = $status;
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
        if ($externalReference !== null) {
            $query['external_reference'] = $externalReference;
        }
        if ($fields !== null) {
            $query['fields'] = $fields;
        }

        return $this->request('GET', '/invoices', query: $query, company: $company);
    }

    /**
     * Every attempt made for one invoice, with what the authorizer answered.
     *
     * consult() gives the status; this gives the reason. Takes the
     * invoiceId, like reissue() and unlike everything else, because a
     * rejected document has no access key to look it up by.
     *
     * @return array<int, array<string, mixed>>
     */
    public function submissions(string $invoiceId, ?string $company = null): array
    {
        $rows = $this->request(
            'GET',
            "/invoices/{$invoiceId}/submissions",
            company: $company,
        );
        if (!array_is_list($rows)) {
            throw new InvoiceError('unexpected response shape: expected a list');
        }

        return $rows;
    }

    /**
     * The numbering ranges this company already invalidated, a page.
     *
     * Each row carries the range, the reason, the SEFAZ status and its
     * protocol. Read it before invalidate() to avoid reporting the same
     * range twice.
     *
     * @return array<string, mixed>
     */
    public function invalidations(
        ?int $limit = null,
        ?int $offset = null,
        ?string $sortBy = null,
        ?string $orderBy = null,
        ?string $company = null,
    ): array {
        return $this->request(
            'GET',
            '/invoices/invalidations',
            query: self::pageQuery($limit, $offset, $sortBy, $orderBy),
            company: $company,
        );
    }

    /**
     * Why a rejected document was refused, and what to change.
     *
     * submissions() has the authorizer's own answer; this reads it for
     * someone who does not speak SEFAZ: code, message, explanation,
     * steps, the field to fix and, when there is one, a suggested
     * value. Takes the invoiceId, like submissions(). A document that
     * is not rejected is an ApiError 409.
     *
     * @return array<string, mixed>
     */
    public function rejection(string $invoiceId, ?string $company = null): array
    {
        return $this->request(
            'GET',
            '/invoices/' . FiscalReference::segment($invoiceId) . '/rejection',
            company: $company,
        );
    }

    /**
     * A suggested fiscal classification for one item's description.
     *
     * A suggestion, never a decision: primary_candidate and
     * alternative_candidates carry the NCM, CFOP, CST/CSOSN, CEST or
     * service code, each read from the published tables, with a
     * confidence, and missing_information lists what would settle it
     * (pass those back in $answers). Someone confirms it before it goes
     * on an item; the codes decide the tax owed.
     *
     * @param array<string, mixed> $answers
     * @return array<string, mixed>
     */
    public function classify(
        string $description,
        DocumentType $documentType,
        ?string $recipientState = null,
        array $answers = [],
        ?string $company = null,
    ): array {
        $length = mb_strlen($description);
        if ($length < 3 || $length > 500) {
            throw new InvoiceError('description must be 3 to 500 characters');
        }
        if ($recipientState !== null && mb_strlen($recipientState) !== 2) {
            throw new InvoiceError('recipientState must be a two-letter UF');
        }

        $payload = [
            'description' => $description,
            'document_type' => $documentType->value,
        ];
        if ($recipientState !== null) {
            $payload['recipient_state'] = strtoupper($recipientState);
        }
        if ($answers !== []) {
            $payload['answers'] = $answers;
        }

        return $this->request('POST', '/ai/classify', json: $payload, company: $company);
    }

    /**
     * Counts of this company's documents, and the last $days of them.
     *
     * total, by_document_type and by_status cover every document;
     * current_period/previous_period compare the last $days (1 to 365,
     * the API's default 30) with the ones before, and daily_series has
     * one row per day.
     *
     * @return array<string, mixed>
     */
    public function stats(?int $days = null, ?string $company = null): array
    {
        $query = [];
        if ($days !== null) {
            if ($days < 1 || $days > 365) {
                throw new InvoiceError('days must be 1 to 365');
            }
            $query['days'] = (string) $days;
        }

        return $this->request('GET', '/invoices/stats', query: $query, company: $company);
    }

    /**
     * The recipient's formal answer to a received document.
     *
     * Only OPERACAO_NAO_REALIZADA takes a reason, and it requires one.
     * Both are fixed rules, checked here rather than spending a round
     * trip to be told.
     *
     * @return array<string, mixed>
     */
    public function manifest(
        string $accessKey,
        Manifestation $manifestation,
        ?string $reason = null,
        ?string $company = null,
    ): array {
        $needsReason = $manifestation === Manifestation::OPERACAO_NAO_REALIZADA;
        if ($needsReason && ($reason === null || $reason === '')) {
            throw new InvoiceError(
                'manifestation 210240 (operacao nao realizada) requires a reason'
            );
        }
        if (!$needsReason && $reason !== null && $reason !== '') {
            throw new InvoiceError(
                "manifestation {$manifestation->value} does not take a reason"
            );
        }

        $payload = ['manifestation' => $manifestation->value];
        if ($reason !== null && $reason !== '') {
            $payload['reason'] = $reason;
        }

        return $this->request(
            'POST',
            "/received-invoices/{$accessKey}/manifestation",
            json: $payload,
            company: $company,
        );
    }

    /**
     * The printable PDF of an authorized document, as raw bytes — the
     * only method that does not return a parsed array.
     *
     * The API draws it itself: the DANFE for an NF-e, the DANFSe for an
     * NFS-e. The XML stays the legally valid document; this is its
     * human-readable representation.
     */
    public function pdf(
        string $accessKey,
        DocumentType $documentType,
        ?string $company = null,
    ): string {
        return $this->send(
            'GET',
            "/invoices/{$accessKey}/pdf",
            query: ['document_type' => $documentType->value],
            company: $company,
        );
    }
}
