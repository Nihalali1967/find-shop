<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductSearch
{
    public const SORTS = ['relevance', 'newest', 'oldest', 'price_asc', 'price_desc', 'name_asc'];

    public function __construct(private readonly SearchQueryParser $parser) {}

    /**
     * @return array{terms:array<int,array>,tokens:array<int,string>,correction:?array,original_query:?string}
     */
    public function parseQuery(?string $query): array
    {
        return $this->parser->parse($query);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function query(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $parsed = $this->parseQuery($filters['q'] ?? null);
        $terms = $parsed['terms'];
        $hasSearch = $terms !== [];

        $builder = Product::query()->visible()
            ->with(['shop', 'subcategory.category', 'unit', 'colors', 'images']);

        if ($hasSearch) {
            $builder->join('product_search_documents as psd', 'psd.product_id', '=', 'products.id')
                ->select('products.*');

            foreach ($terms as $term) {
                $builder->where(function (Builder $q) use ($term) {
                    foreach (array_values($term['variants']) as $index => $variant) {
                        $like = '%'.$this->escapeLike($variant).'%';
                        $index === 0
                            ? $q->where('psd.document', 'like', $like)
                            : $q->orWhere('psd.document', 'like', $like);
                    }
                });
            }
        }

        $this->applyFilters($builder, $filters);
        $this->applySort($builder, $filters['sort'] ?? null, $terms, $hasSearch);

        return $builder->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    protected function applyFilters(Builder $builder, array $filters): void
    {
        if (! empty($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];
            $builder->whereHas('subcategory', fn (Builder $q) => $q->where('category_id', $categoryId));
        }

        if (! empty($filters['subcategory_id'])) {
            $builder->where('products.subcategory_id', (int) $filters['subcategory_id']);
        }

        if (! empty($filters['colors']) && is_array($filters['colors'])) {
            $colorIds = array_filter(array_map('intval', $filters['colors']));
            if ($colorIds !== []) {
                // Multiple selected colors mean ANY of them initially.
                $builder->whereHas('colors', fn (Builder $q) => $q->whereIn('colors.id', $colorIds));
            }
        }

        if (! empty($filters['shop_id'])) {
            $builder->where('products.shop_id', (int) $filters['shop_id']);
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== null && $filters['min_price'] !== '') {
            $builder->whereRaw($this->effectivePrice().' >= ?', [(int) $filters['min_price']]);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== null && $filters['max_price'] !== '') {
            $builder->whereRaw($this->effectivePrice().' <= ?', [(int) $filters['max_price']]);
        }
    }

    /**
     * @param  array<int,array>  $terms
     */
    protected function applySort(Builder $builder, ?string $sort, array $terms, bool $hasSearch): void
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : ($hasSearch ? 'relevance' : 'newest');

        if ($sort === 'relevance' && $terms !== []) {
            [$sql, $bindings] = $this->relevanceExpression($terms);
            $builder->orderByRaw("({$sql}) DESC", $bindings);
            $builder->orderByDesc('products.created_at')->orderByDesc('products.id');

            return;
        }

        match ($sort) {
            'price_asc' => $builder->orderByRaw($this->effectivePrice().' ASC')->orderBy('products.id'),
            'price_desc' => $builder->orderByRaw($this->effectivePrice().' DESC')->orderBy('products.id'),
            'oldest' => $builder->orderBy('products.created_at')->orderBy('products.id'),
            'name_asc' => $builder->orderBy('products.name')->orderBy('products.id'),
            default => $builder->orderByDesc('products.created_at')->orderByDesc('products.id'),
        };
    }

    /**
     * Weighted score: name/title > category/subcategory > colors.
     *
     * @param  array<int,array>  $terms
     * @return array{0:string,1:array<int,string>}
     */
    protected function relevanceExpression(array $terms): array
    {
        $parts = [];
        $bindings = [];

        $name = $this->jsonValue('field_map', '$.name');
        $title = $this->jsonValue('field_map', '$.title');
        $category = $this->jsonValue('field_map', '$.category');
        $subcategory = $this->jsonValue('field_map', '$.subcategory');
        $colors = $this->jsonValue('field_map', '$.colors');

        foreach ($terms as $term) {
            foreach (array_values($term['variants']) as $variant) {
                $like = '%'.$this->escapeLike($variant).'%';

                $parts[] = "(CASE WHEN {$name} LIKE ? OR {$title} LIKE ? THEN 3 ELSE 0 END)";
                $parts[] = "(CASE WHEN {$category} LIKE ? OR {$subcategory} LIKE ? THEN 2 ELSE 0 END)";
                $parts[] = "(CASE WHEN {$colors} LIKE ? THEN 1 ELSE 0 END)";
                array_push($bindings, $like, $like, $like, $like, $like);
            }
        }

        return [implode(' + ', $parts), $bindings];
    }

    protected function jsonValue(string $column, string $path): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "json_extract(psd.{$column}, '{$path}')"
            : "JSON_UNQUOTE(JSON_EXTRACT(psd.{$column}, '{$path}'))";
    }

    protected function effectivePrice(): string
    {
        return 'COALESCE(products.offer_price_paise, products.price_paise)';
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
