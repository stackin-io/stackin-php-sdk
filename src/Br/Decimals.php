<?php

declare(strict_types=1);

namespace Stackin\Br;

use Stackin\Errors\InvoiceError;

/**
 * Amounts and weights as the wire takes them: a decimal string, never a
 * binary float's own digits.
 */
final class Decimals
{
    public static function format(float|string $value, string $field, ?int $places = null): string
    {
        $text = is_float($value)
            ? rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.')
            : trim($value);
        if (preg_match('/^\d+(\.\d+)?$/', $text) !== 1) {
            throw new InvoiceError("{$field} must be a non-negative decimal, such as 12.50");
        }
        $dot = strpos($text, '.');
        if ($places !== null && $dot !== false && strlen($text) - $dot - 1 > $places) {
            throw new InvoiceError("{$field} takes at most {$places} decimal places");
        }

        return $text;
    }

    public static function optional(
        float|string|null $value,
        string $field,
        ?int $places = null,
    ): ?string {
        return $value === null ? null : self::format($value, $field, $places);
    }
}
