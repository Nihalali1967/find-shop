<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Services\Catalog\ProductSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Get all active categories
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Electronics",
     *       "slug": "electronics",
     *       "subcategory_count": 5
     *     }
     *   ]
     * }
     */
    public function categories(): JsonResponse
    {
        $categories = Category::query()->active()->orderBy('sort_order')->get();

        return response()->json([
            'data' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'subcategory_count' => $c->subcategories()->where('status', 'active')->count(),
            ]),
        ]);
    }

    /**
     * Get subcategories for a category
     *
     * @urlParam category int required Category ID. Example: 1
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Mobile Phones",
     *       "slug": "mobile-phones"
     *     }
     *   ]
     * }
     */
    public function subcategories(Category $category): JsonResponse
    {
        $subcategories = $category->subcategories()->where('status', 'active')->get();

        return response()->json([
            'data' => $subcategories->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug]),
        ]);
    }

    /**
     * Get all active colors
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Red",
     *       "hex": "#FF0000",
     *       "multicolor": false
     *     }
     *   ]
     * }
     */
    public function colors(): JsonResponse
    {
        return response()->json([
            'data' => Color::query()->active()->orderBy('sort_order')->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'hex' => $c->hex,
                'multicolor' => (bool) $c->is_multicolor,
            ]),
        ]);
    }

    /**
     * Get all active units
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Piece",
     *       "code": "pcs",
     *       "requires_custom": false
     *     }
     *   ]
     * }
     */
    public function units(): JsonResponse
    {
        return response()->json([
            'data' => Unit::query()->active()->orderBy('sort_order')->get()->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'code' => $u->code,
                'requires_custom' => (bool) $u->requires_custom,
            ]),
        ]);
    }

    /**
     * Search and filter products
     *
     * @queryParam q string Search query. Example: iphone
     * @queryParam category_id int Filter by category ID. Example: 1
     * @queryParam subcategory_id int Filter by subcategory ID. Example: 2
     * @queryParam colors array Filter by color IDs. Example: [1,2,3]
     * @queryParam min_price int Minimum price in paise. Example: 10000
     * @queryParam max_price int Maximum price in paise. Example: 50000
     * @queryParam sort string Sort order. Must be one of: newest, price_asc, price_desc, relevance. Example: newest
     * @queryParam per_page int Items per page (1-48). Example: 12
     * @response 200 {
     *   "data": [...],
     *   "meta": {
     *     "correction": null,
     *     "original_query": "iphone",
     *     "filters": {...},
     *     "sort": "relevance"
     *   }
     * }
     */
    public function products(Request $request, ProductSearch $search): JsonResponse
    {
        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:subcategories,id'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['integer', 'exists:colors,id'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'in:'.implode(',', ProductSearch::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $filters = [
            'q' => $request->input('q'),
            'category_id' => $request->integer('category_id') ?: null,
            'subcategory_id' => $request->integer('subcategory_id') ?: null,
            'colors' => (array) $request->input('colors', []),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'sort' => $request->input('sort'),
            'shop_id' => $request->integer('shop_id') ?: null,
        ];

        $paginator = $search->query($filters, (int) $request->input('per_page', 12));
        $parsed = $search->parseQuery($filters['q']);

        return ProductResource::collection($paginator)->additional([
            'meta' => [
                'correction' => $parsed['correction'],
                'original_query' => $parsed['original_query'],
                'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== []),
                'sort' => $filters['sort'] ?? (($parsed['terms'] ?? []) !== [] ? 'relevance' : 'newest'),
            ],
        ])->response();
    }

    /**
     * Get a single product by ID
     *
     * @urlParam product int required Product ID. Example: 1
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Product Name",
     *     "description": "Description",
     *     "price_paise": 10000,
     *     ...
     *   }
     * }
     * @response 404 Product not found
     */
    public function product(Product $product): JsonResponse
    {
        abort_unless($product->status === Product::STATUS_PUBLISHED, 404);
        $product->load(['shop', 'subcategory.category', 'unit', 'colors', 'images']);

        abort_unless($product->shop?->isActive(), 404);
        abort_unless($product->subcategory?->status === 'active' && $product->subcategory?->category?->status === 'active', 404);

        return response()->json(['data' => new ProductResource($product)]);
    }

    /**
     * Get shop details
     *
     * @urlParam shop int required Shop ID. Example: 1
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Shop Name",
     *     "locality": "City",
     *     "pincode": "123456",
     *     "status": "active",
     *     "product_count": 10,
     *     "joined_at": "2024-01-01T00:00:00Z"
     *   }
     * }
     * @response 404 Shop not found or inactive
     */
    public function shop(Shop $shop): JsonResponse
    {
        abort_unless($shop->isActive(), 404);

        return response()->json([
            'data' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'locality' => $shop->locality,
                'pincode' => $shop->pincode,
                'status' => $shop->status,
                'product_count' => $shop->products()->where('status', Product::STATUS_PUBLISHED)->count(),
                'joined_at' => $shop->created_at?->toIso8601String(),
            ],
        ]);
    }
}
