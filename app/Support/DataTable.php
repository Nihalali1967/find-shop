<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Shared helpers for the server-rendered DataTable listings used across
 * the admin, shop and client portals. Sorting columns are always matched
 * against a per-controller allowlist before reaching the query builder.
 */
class DataTable
{
    /**
     * Clamp the requested per-page size to a safe range.
     */
    public static function perPage(Request $request, int $default = 10, int $max = 100): int
    {
        $perPage = (int) $request->input('per_page', $default);

        if ($perPage < 1) {
            $perPage = $default;
        }

        return max(5, min($max, $perPage));
    }

    /**
     * Escaped LIKE pattern for user input (safe for MySQL and SQLite).
     */
    public static function like(?string $value): string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return '%';
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $trimmed);

        return '%'.$escaped.'%';
    }

    /**
     * Resolve the requested sort direction ('asc' or 'desc' only).
     */
    public static function direction(Request $request): string
    {
        return $request->input('dir') === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Return the requested sort column when it is allowlisted, else null.
     *
     * @param  array<int, string>  $allowlist
     */
    public static function sortable(Request $request, array $allowlist): ?string
    {
        $sort = $request->input('sort');

        return in_array($sort, $allowlist, true) ? $sort : null;
    }
}
