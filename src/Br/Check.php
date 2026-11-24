<?php

declare(strict_types=1);

namespace Stackin\Br;

use DateTimeInterface;
use Stackin\Errors\InvoiceError;

/**
 * The shape rules the NFS-e groups check when they are built: patterns,
 * lengths, enumerations, ranges and decimal places. Each failure raises an
 * InvoiceError naming the field.
 */
final class Check
{
    public const LIST_PIS_COFINS_CST = [
        '00', '01', '02', '03', '04', '05', '06', '07', '08', '09',
        '49', '50', '51', '52', '53', '54', '55', '56',
        '60', '61', '62', '63', '64', '65', '66', '67',
        '70', '71', '72', '73', '74', '75', '98', '99',
    ];

    public static function fail(string $field, string $message): never
    {
        throw new InvoiceError("{$field} {$message}");
    }

    public static function has(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    public static function count(bool ...$flags): int
    {
        return count(array_filter($flags));
    }

    public static function unmask(?string $value, bool $upper = false): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = (string) preg_replace('/[\s.\/-]/', '', $value);

        return $upper ? strtoupper($text) : $text;
    }

    public static function phone(?string $value): ?string
    {
        return $value === null ? null : (string) preg_replace('/[\s().-]/', '', $value);
    }

    public static function pad2(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim($value);

        return strlen($text) === 1 && ctype_digit($text) ? '0' . $text : $text;
    }

    public static function oneLine(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', $parts === false ? [] : $parts);
    }

    public static function text(?string $value, string $field, int $min, int $max): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $text = trim($value);
        $length = mb_strlen($text);
        if ($length < $min || $length > $max) {
            self::fail($field, "must be {$min} to {$max} characters");
        }

        return $text;
    }

    public static function required(?string $value, string $field, int $min, int $max): string
    {
        $text = self::text($value, $field, $min, $max);
        if ($text === null) {
            self::fail($field, 'is required');
        }

        return $text;
    }

    public static function pattern(
        ?string $value,
        string $field,
        string $regex,
        string $want,
    ): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $text = trim($value);
        if (preg_match($regex, $text) !== 1) {
            self::fail($field, "must be {$want}");
        }

        return $text;
    }

    public static function requiredPattern(
        ?string $value,
        string $field,
        string $regex,
        string $want,
    ): string {
        $text = self::pattern($value, $field, $regex, $want);
        if ($text === null) {
            self::fail($field, 'is required');
        }

        return $text;
    }

    /**
     * @param list<string> $allowed
     */
    public static function choice(?string $value, string $field, array $allowed): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $text = trim($value);
        if (!in_array($text, $allowed, true)) {
            self::fail($field, 'must be one of ' . implode(', ', $allowed) . ", not '{$text}'");
        }

        return $text;
    }

    /**
     * @param list<string> $allowed
     */
    public static function requiredChoice(?string $value, string $field, array $allowed): string
    {
        $text = self::choice($value, $field, $allowed);
        if ($text === null) {
            self::fail($field, 'is required');
        }

        return $text;
    }

    /**
     * @param list<int> $allowed
     */
    public static function integer(?int $value, string $field, array $allowed): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            self::fail($field, 'must be one of ' . implode(', ', $allowed) . ", not {$value}");
        }

        return $value;
    }

    /**
     * @param list<int> $allowed
     */
    public static function requiredInteger(?int $value, string $field, array $allowed): int
    {
        $number = self::integer($value, $field, $allowed);
        if ($number === null) {
            self::fail($field, 'is required');
        }

        return $number;
    }

    public static function requiredIntegerRange(
        ?int $value,
        string $field,
        int $min,
        int $max,
    ): int {
        if ($value === null) {
            self::fail($field, 'is required');
        }
        if ($value < $min || $value > $max) {
            self::fail($field, "must be {$min} to {$max}");
        }

        return $value;
    }

    public static function decimal(
        float|string|null $value,
        string $field,
        float $low,
        bool $lowOpen,
        float $high,
        int $places,
    ): ?string {
        if ($value === null) {
            return null;
        }
        $text = Decimals::format($value, $field, $places);
        $number = (float) $text;
        if ($number < $low || ($lowOpen && $number <= $low) || $number > $high) {
            $floor = ($lowOpen ? 'above ' : 'at least ') . self::plain($low);
            self::fail($field, "must be {$floor} and at most " . self::plain($high));
        }

        return $text;
    }

    public static function requiredDecimal(
        float|string|null $value,
        string $field,
        float $low,
        bool $lowOpen,
        float $high,
        int $places,
    ): string {
        $text = self::decimal($value, $field, $low, $lowOpen, $high, $places);
        if ($text === null) {
            self::fail($field, 'is required');
        }

        return $text;
    }

    public static function number(
        ?float $value,
        string $field,
        float $low,
        float $high,
    ): ?float {
        if ($value === null) {
            return null;
        }
        if ($value < $low || $value > $high) {
            self::fail(
                $field,
                'must be at least ' . self::plain($low) . ' and at most ' . self::plain($high),
            );
        }

        return $value;
    }

    public static function requiredNumber(
        ?float $value,
        string $field,
        float $low,
        float $high,
    ): float {
        $number = self::number($value, $field, $low, $high);
        if ($number === null) {
            self::fail($field, 'is required');
        }

        return $number;
    }

    public static function day(DateTimeInterface|string|null $value, string $field): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            $text = $value->format('Y-m-d');
        } else {
            $text = trim($value);
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $text);
            if ($parsed === false || $parsed->format('Y-m-d') !== $text) {
                self::fail($field, 'must be a date as YYYY-MM-DD');
            }
        }
        $year = (int) substr($text, 0, 4);
        if ($year < 2000 || $year > 2099) {
            self::fail($field, 'must be a date from 2000 to 2099');
        }

        return $text;
    }

    public static function requiredDay(DateTimeInterface|string|null $value, string $field): string
    {
        $text = self::day($value, $field);
        if ($text === null) {
            self::fail($field, 'is required');
        }

        return $text;
    }

    /**
     * @param array<array-key, mixed>|null $items
     * @param class-string $class
     * @return list<object>|null
     */
    public static function objects(?array $items, string $field, int $max, string $class): ?array
    {
        if ($items === null || $items === []) {
            return null;
        }
        if (count($items) > $max) {
            self::fail($field, "takes 1 to {$max} entries");
        }
        $list = [];
        foreach (array_values($items) as $index => $item) {
            if (!$item instanceof $class) {
                self::fail("{$field}[{$index}]", 'must be a ' . $class);
            }
            $list[] = $item;
        }

        return $list;
    }

    /**
     * @param list<object>|null $objects
     * @return list<array<string, mixed>>|null
     */
    public static function arrays(?array $objects): ?array
    {
        if ($objects === null) {
            return null;
        }

        return array_map(
            static fn (object $object): array => $object->toArray(),
            $objects,
        );
    }

    /**
     * @param array<array-key, mixed>|null $items
     * @return list<string>|null
     */
    public static function strings(
        ?array $items,
        string $field,
        int $max,
        int $min,
        int $maxLength,
    ): ?array {
        if ($items === null || $items === []) {
            return null;
        }
        if (count($items) > $max) {
            self::fail($field, "takes at most {$max} entries");
        }
        $list = [];
        foreach (array_values($items) as $index => $item) {
            $list[] = self::required(
                is_string($item) ? $item : null,
                "{$field}[{$index}]",
                $min,
                $maxLength,
            );
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function compact(array $fields): array
    {
        return array_filter($fields, static fn (mixed $value): bool => $value !== null);
    }

    private static function plain(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
