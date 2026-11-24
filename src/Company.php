<?php

declare(strict_types=1);

namespace Stackin;

use Stackin\Errors\InvoiceError;
use stdClass;

/**
 * The issuer itself: fixes what readiness() reports missing.
 *
 * Reading stays Client::readiness(); this type only writes. Company data,
 * so update() takes $company for one call over the client's own. An OAuth
 * credential needs company:write.
 */
final class Company extends Client
{
    /**
     * The address keys PATCH /company accepts. There is no complement, and
     * state changes only in the dashboard: it decides the authorizer.
     */
    private const ADDRESS_KEYS = [
        'street',
        'number',
        'neighborhood',
        'municipality_code',
        'municipality_name',
        'zip_code',
    ];

    private const FISCAL_KEYS = [
        'tax_regime',
        'simples_nacional',
        'simples_apuracao',
        'simples_apuracao_ibs_cbs',
        'state_registration',
        'service_code',
    ];

    /** The fiscal fields with a fixed enumeration, and its values. */
    private const FISCAL_CODES = [
        'tax_regime' => ['1', '2', '3', '4'],
        'simples_nacional' => ['1', '2', '3'],
        'simples_apuracao' => ['1', '2', '3'],
        'simples_apuracao_ibs_cbs' => ['1', '3'],
    ];

    /**
     * Fixes the issuer's address and, for the company owner, its fiscal
     * fields (PATCH /company).
     *
     * $address takes street, number, neighborhood, municipality_code (the
     * 7-digit IBGE code of a city in the company's state), municipality_name
     * (derived from the code when left out) and zip_code (8 digits, a mask
     * accepted). $fiscalFields takes tax_regime (CRT 1-4), simples_nacional
     * (1-3), simples_apuracao (1-3), simples_apuracao_ibs_cbs (1 or 3),
     * state_registration and service_code;
     * anyone but the owner is refused with an ApiError 403. Any other key is
     * refused here, before the call. Null values are left out, and a call
     * with nothing changes nothing and answers the company as it is.
     *
     * $blockActivityMismatch (owner only) true makes the API refuse an
     * issuance with MEI_ACTIVITY_NOT_ALLOWED or ACTIVITY_NOT_REGISTERED as a
     * 422 instead of a warning; false turns it back off. It is sent only when
     * not null.
     *
     * Returns every readiness() field plus address and fiscal_fields as
     * stored, so it says whether the company can issue now.
     *
     * @param array<string, string|null>|null $address
     * @param array<string, string|null>|null $fiscalFields
     * @return array<string, mixed>
     */
    public function update(
        ?array $address = null,
        ?array $fiscalFields = null,
        ?string $company = null,
        ?bool $blockActivityMismatch = null,
    ): array {
        $payload = [];

        if ($blockActivityMismatch !== null) {
            $payload['block_activity_mismatch'] = $blockActivityMismatch;
        }

        $address = self::group('address', $address, self::ADDRESS_KEYS);
        if ($address !== []) {
            $code = $address['municipality_code'] ?? null;
            if ($code !== null && preg_match('/^[0-9]{7}$/', $code) !== 1) {
                throw new InvoiceError('municipality_code must be the 7-digit IBGE code');
            }
            if (isset($address['zip_code'])) {
                $digits = (string) preg_replace('/[\s.\-]/', '', $address['zip_code']);
                if (preg_match('/^[0-9]{8}$/', $digits) !== 1) {
                    throw new InvoiceError('zip_code must be the 8-digit CEP');
                }
                $address['zip_code'] = $digits;
            }
            $payload['address'] = $address;
        }

        $fiscal = self::group('fiscal_fields', $fiscalFields, self::FISCAL_KEYS);
        if ($fiscal !== []) {
            foreach (self::FISCAL_CODES as $key => $allowed) {
                if (isset($fiscal[$key]) && !in_array($fiscal[$key], $allowed, true)) {
                    throw new InvoiceError(
                        "{$key} must be one of " . implode(', ', $allowed),
                    );
                }
            }
            $payload['fiscal_fields'] = $fiscal;
        }

        /** @var array<string, mixed> */
        return $this->request(
            'PATCH',
            '/company',
            json: $payload === [] ? new stdClass() : $payload,
            company: $company,
        );
    }

    /**
     * The group without its nulls, refusing any key the API would refuse.
     *
     * @param array<string, string|null>|null $fields
     * @param list<string> $known
     * @return array<string, string>
     */
    private static function group(string $name, ?array $fields, array $known): array
    {
        if ($fields === null) {
            return [];
        }
        $unknown = array_values(array_diff(array_keys($fields), $known));
        if ($unknown !== []) {
            sort($unknown);
            throw new InvoiceError(
                "unknown {$name} keys: " . implode(', ', $unknown)
                . '. Known: ' . implode(', ', $known),
            );
        }

        return array_filter($fields, static fn (?string $value): bool => $value !== null);
    }
}
