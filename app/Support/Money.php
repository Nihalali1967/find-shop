<?php

namespace App\Support;

class Money
{
    /**
     * Convert a validated decimal rupee string (or int paise) to integer paise.
     */
    public static function toPaise(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $string = trim((string) $value);
        $string = str_replace([',', ' ', '₹'], '', $string);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $string)) {
            return null;
        }

        [$rupees, $decimals] = array_pad(explode('.', $string, 2), 2, '0');
        $decimals = str_pad(substr($decimals, 0, 2), 2, '0');

        return ((int) $rupees) * 100 + (int) $decimals;
    }

    /**
     * Format integer paise as an Indian-grouped rupee string, e.g. 1234567 -> ₹12,345.67
     */
    public static function format(?int $paise, bool $withSymbol = true): string
    {
        $paise ??= 0;
        $negative = $paise < 0;
        $paise = abs($paise);

        $rupees = intdiv($paise, 100);
        $decimals = $paise % 100;

        $formatted = self::groupIndian((string) $rupees).'.'.str_pad((string) $decimals, 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').($withSymbol ? '₹' : '').$formatted;
    }

    public static function groupIndian(string $digits): string
    {
        $length = strlen($digits);

        if ($length <= 3) {
            return $digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;

        return $rest.','.$last3;
    }

    /**
     * Render a stored quantity decimal string without trailing noise, e.g. "2.000" -> "2".
     */
    public static function quantity(string|float|null $value): string
    {
        $formatted = number_format((float) ($value ?? 0), 3, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
