<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Color;
use App\Models\SearchAlias;
use App\Models\Subcategory;
use App\Support\NameNormalizer;

class SearchQueryParser
{
    /** @var array<string,array<int,string>>|null */
    private ?array $aliasMap = null;

    /** @var array<string,string>|null */
    private ?array $vocabulary = null;

    /**
     * @return array{
     *   terms: array<int,array{original:string,canonical:string,variants:array<int,string>}>,
     *   tokens: array<int,string>,
     *   correction: ?array{from:string,to:string},
     *   original_query: ?string
     * }
     */
    public function parse(?string $query): array
    {
        $original = $query;
        $query = NameNormalizer::normalize($query);
        $query = mb_substr($query, 0, (int) config('marketplace.search.max_query_length'));

        if ($query === '') {
            return ['terms' => [], 'tokens' => [], 'correction' => null, 'original_query' => null];
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));
        $tokens = array_slice($tokens, 0, (int) config('marketplace.search.max_tokens'));

        $terms = [];
        $correction = null;

        foreach ($tokens as $token) {
            $canonical = $this->resolveAlias($token);
            $variants = [$token];

            if ($canonical !== null) {
                $variants[] = $canonical;
                $variants = array_merge($variants, $this->synonymsFor($canonical));
                $canonical = $canonical;
            } else {
                $corrected = $this->correctTypo($token);

                if ($corrected !== null && $corrected !== $token) {
                    $variants[] = $corrected;
                    $variants = array_merge($variants, $this->synonymsFor($corrected));
                    $correction ??= ['from' => $token, 'to' => $corrected];
                    $canonical = $corrected;
                } else {
                    $canonical = $token;
                }
            }

            $terms[] = [
                'original' => $token,
                'canonical' => $canonical,
                'variants' => array_values(array_unique(array_filter($variants))),
            ];
        }

        return [
            'terms' => $terms,
            'tokens' => $tokens,
            'correction' => $correction,
            'original_query' => $original,
        ];
    }

    private function resolveAlias(string $token): ?string
    {
        $map = $this->aliasMap();

        return $map[$token][0] ?? null;
    }

    /**
     * @return array<int,string>
     */
    private function synonymsFor(string $canonical): array
    {
        $map = $this->aliasMap();

        return $map[$canonical] ?? [];
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function aliasMap(): array
    {
        if ($this->aliasMap !== null) {
            return $this->aliasMap;
        }

        $map = [];

        foreach (SearchAlias::query()->get() as $alias) {
            $term = NameNormalizer::key($alias->term);
            $canonical = NameNormalizer::key($alias->canonical);

            if ($term === '' || $canonical === '') {
                continue;
            }

            $map[$term] ??= [];
            if (! in_array($canonical, $map[$term], true)) {
                $map[$term][] = $canonical;
            }

            // Keep both directions so "flooring" also resolves to "floor".
            if ($alias->type === 'alias') {
                $map[$canonical] ??= [];
                if (! in_array($term, $map[$canonical], true)) {
                    $map[$canonical][] = $term;
                }
            }
        }

        return $this->aliasMap = $map;
    }

    /**
     * Controlled vocabulary used for bounded typo correction.
     *
     * @return array<string,string>
     */
    private function vocabulary(): array
    {
        if ($this->vocabulary !== null) {
            return $this->vocabulary;
        }

        $vocabulary = [];

        foreach (array_keys($this->aliasMap()) as $term) {
            $vocabulary[$term] = $term;
        }

        foreach (Color::query()->pluck('name_key') as $name) {
            $vocabulary[NameNormalizer::key($name)] = NameNormalizer::key($name);
        }

        foreach (Category::query()->pluck('name_key') as $name) {
            $vocabulary[NameNormalizer::key($name)] = NameNormalizer::key($name);
        }

        foreach (Subcategory::query()->pluck('name_key') as $name) {
            $vocabulary[NameNormalizer::key($name)] = NameNormalizer::key($name);
        }

        return $this->vocabulary = $vocabulary;
    }

    /**
     * Return a unique near match, or null when ambiguous / too far.
     */
    private function correctTypo(string $token): ?string
    {
        $minLength = (int) config('marketplace.search.min_typo_word_length');
        $maxDistance = (int) config('marketplace.search.max_typo_distance');

        if (mb_strlen($token) < $minLength) {
            return null;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $ambiguous = false;

        foreach ($this->vocabulary() as $candidate) {
            if (abs(mb_strlen($candidate) - mb_strlen($token)) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($token, $candidate);

            if ($distance > $maxDistance) {
                continue;
            }

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
                $ambiguous = false;
            } elseif ($distance === $bestDistance && $candidate !== $best) {
                $ambiguous = true;
            }
        }

        return $ambiguous ? null : $best;
    }
}
