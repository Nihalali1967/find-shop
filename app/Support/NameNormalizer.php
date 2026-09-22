<?php

namespace App\Support;

use Illuminate\Support\Str;

class NameNormalizer
{
    /**
     * Normalize a display name: unicode-normalize, trim and collapse whitespace.
     */
    public static function normalize(?string $value): string
    {
        $value = (string) $value;

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Deterministic uniqueness key: normalized + lowercased (multibyte aware).
     */
    public static function key(?string $value): string
    {
        return mb_strtolower(self::normalize($value), 'UTF-8');
    }

    public static function slug(?string $value): string
    {
        $slug = Str::slug(self::normalize($value));

        return $slug !== '' ? $slug : Str::lower(Str::random(8));
    }

    /**
     * Normalize a phone number to E.164 (defaults to +91 for 10-digit Indian input).
     */
    public static function phone(?string $value, string $defaultCountry = '91'): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with((string) $value, '+')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+'.$defaultCountry.$digits;
        }

        if (str_starts_with($digits, $defaultCountry) && strlen($digits) === strlen($defaultCountry) + 10) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
