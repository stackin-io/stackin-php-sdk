<?php

declare(strict_types=1);

namespace Stackin;

/**
 * What a dry run of issue() found, from POST /invoices/validate.
 *
 * valid is true when no rule failed. Each finding in errors and
 * warnings is an array with code, message and field (null when it is
 * not about one request field); a rule that mirrors a SEFAZ rejection
 * also carries sefaz_rejection, and expected, received or fix_url when
 * the API has them.
 */
final class ValidationResult
{
    /**
     * @param list<array<string, mixed>> $errors
     * @param list<array<string, mixed>> $warnings
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            valid: ($data['valid'] ?? false) === true,
            errors: self::findings($data['errors'] ?? null),
            warnings: self::findings($data['warnings'] ?? null),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function findings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
