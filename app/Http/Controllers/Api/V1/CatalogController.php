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
use App\Services\Catalog\ProductPopularity;
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
     * New arrivals: the latest published products, newest first (default 10)
     *
     * @queryParam limit int Number of products to return (1-48). Example: 10
     * @queryParam category_id int Filter by category ID. Example: 1
     * @response 200 {
     *   "data": [...],
     *   "meta": { "count": 10, "limit": 10 }
     * }
     */
    public function newArrivals(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:48'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $limit = (int) $request->input('limit', 10);

        $products = Product::query()->visible()
            ->with(['shop', 'subcategory.category', 'unit', 'colors', 'images'])
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas(
                'subcategory', fn ($s) => $s->where('category_id', $request->integer('category_id'))
            ))
            ->orderByDesc('products.created_at')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->get();

        return ProductResource::collection($products)->additional([
            'meta' => ['count' => $products->count(), 'limit' => $limit],
        ])->response();
    }

    /**
     * Popular products: ranked by buyer enquiries (chats) and product views in the last N days
     *
     * Score = enquiries × 5 + views. Products with no activity follow, newest first.
     *
     * @queryParam limit int Number of products (1-48). Example: 10
     * @queryParam days int Look-back window in days (1-365). Example: 30
     * @queryParam category_id int Filter by category ID. Example: 1
     * @response 200 {
     *   "data": [ { "id": 1, "name": "...", "popularity": { "score": 23, "views": 13, "enquiries": 2 } } ],
     *   "meta": { "count": 10, "limit": 10, "days": 30 }
     * }
     */
    public function popularProducts(Request $request, ProductPopularity $popularity): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:48'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $limit = (int) $request->input('limit', 10);
        $days = (int) $request->input('days', 30);

        $products = $popularity->query($days)
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas(
                'subcategory', fn ($s) => $s->where('category_id', $request->integer('category_id'))
            ))
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $p) => (new ProductResource($p))->resolve($request) + [
                'popularity' => [
                    'score' => (int) $p->enquiries_count * ProductPopularity::ENQUIRY_WEIGHT + (int) $p->views_count,
                    'views' => (int) $p->views_count,
                    'enquiries' => (int) $p->enquiries_count,
                ],
            ]),
            'meta' => ['count' => $products->count(), 'limit' => $limit, 'days' => $days],
        ]);
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
    public function product(Request $request, Product $product, ProductPopularity $popularity): JsonResponse
    {
        abort_unless($product->status === Product::STATUS_PUBLISHED, 404);
        $product->load(['shop', 'subcategory.category', 'unit', 'colors', 'images']);

        abort_unless($product->shop?->isActive(), 404);
        abort_unless($product->subcategory?->status === 'active' && $product->subcategory?->category?->status === 'active', 404);

        $popularity->recordView($product, $request);

        return response()->json(['data' => new ProductResource($product)]);
    }

    /**
     * List all active shops
     *
     * @queryParam q string Search by shop name. Example: stonecraft
     * @queryParam locality string Filter by locality. Example: Hosur Road
     * @queryParam pincode string Filter by pincode. Example: 560100
     * @queryParam sort string Sort order. Must be one of: newest, name. Example: newest
     * @queryParam per_page int Items per page (1-48). Example: 12
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Shop Name",
     *       "locality": "City",
     *       "pincode": "123456",
     *       "status": "active",
     *       "product_count": 10,
     *       "joined_at": "2024-01-01T00:00:00Z"
     *     }
     *   ]
     * }
     */
    public function shops(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'locality' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'sort' => ['nullable', 'in:newest,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $shops = Shop::query()->visible()
            ->withCount(['products as product_count' => fn ($q) => $q->where('status', Product::STATUS_PUBLISHED)])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$this->escapeLike($request->string('q')).'%'))
            ->when($request->filled('locality'), fn ($q) => $q->where('locality', 'like', '%'.$this->escapeLike($request->string('locality')).'%'))
            ->when($request->filled('pincode'), fn ($q) => $q->where('pincode', $request->string('pincode')))
            ->when(
                $request->input('sort') === 'name',
                fn ($q) => $q->orderBy('name')->orderBy('id'),
                fn ($q) => $q->latest('id')
            )
            ->paginate((int) $request->input('per_page', 12))
            ->withQueryString();

        return response()->json([
            'data' => $shops->getCollection()->map(fn (Shop $shop) => $this->shopPayload($shop)),
            'meta' => [
                'current_page' => $shops->currentPage(),
                'last_page' => $shops->lastPage(),
                'per_page' => $shops->perPage(),
                'total' => $shops->total(),
            ],
            'links' => [
                'first' => $shops->url(1),
                'last' => $shops->url($shops->lastPage()),
                'prev' => $shops->previousPageUrl(),
                'next' => $shops->nextPageUrl(),
            ],
        ]);
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

        return response()->json(['data' => $this->shopPayload($shop->loadCount([
            'products as product_count' => fn ($q) => $q->where('status', Product::STATUS_PUBLISHED),
        ]))]);
    }

    /**
     * Get published products belonging to a shop
     *
     * @urlParam shop int required Shop ID. Example: 1
     * @queryParam sort string Sort order. Must be one of: newest, oldest, price_asc, price_desc, name_asc. Example: newest
     * @queryParam per_page int Items per page (1-48). Example: 12
     * @response 200 {
     *   "data": [...],
     *   "meta": { "current_page": 1, "last_page": 1, "per_page": 12, "total": 3 }
     * }
     * @response 404 Shop not found or inactive
     */
    public function shopProducts(Request $request, Shop $shop, ProductSearch $search): JsonResponse
    {
        abort_unless($shop->isActive(), 404);

        $request->validate([
            'sort' => ['nullable', 'in:'.implode(',', array_diff(ProductSearch::SORTS, ['relevance']))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $filters = [
            'shop_id' => $shop->id,
            'sort' => $request->input('sort'),
        ];

        $paginator = $search->query($filters, (int) $request->input('per_page', 12));

        return ProductResource::collection($paginator)->additional([
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'sort' => $filters['sort'] ?? 'newest',
            ],
        ])->response();
    }

    /**
     * Active shops near a location, closest first
     *
     * Only shops that have saved latitude/longitude are included.
     *
     * @queryParam lat float required User latitude. Example: 12.9716
     * @queryParam lng float required User longitude. Example: 77.5946
     * @queryParam radius_km float Search radius in km (0.5-100). Example: 10
     * @queryParam limit int Max shops to return (1-50). Example: 20
     * @response 200 {
     *   "data": [
     *     { "id": 1, "name": "Shop Name", "locality": "City", "pincode": "560100", "status": "active",
     *       "product_count": 10, "joined_at": "...", "latitude": 12.97, "longitude": 77.59, "distance_km": 1.42 }
     *   ],
     *   "meta": { "lat": 12.9716, "lng": 77.5946, "radius_km": 10, "count": 1 }
     * }
     */
    public function nearbyShops(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $radius = (float) $request->input('radius_km', 10);
        $limit = (int) $request->input('limit', 20);

        // Cheap bounding-box prefilter in SQL; exact distance is computed below.
        $latDelta = $radius / 111.045;
        $lngDelta = $radius / (111.045 * max(cos(deg2rad($lat)), 0.01));

        $shops = Shop::query()->visible()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->withCount(['products as product_count' => fn ($q) => $q->where('status', Product::STATUS_PUBLISHED)])
            ->get()
            ->map(function (Shop $shop) use ($lat, $lng) {
                $shop->distance_km = $this->distanceKm($lat, $lng, (float) $shop->latitude, (float) $shop->longitude);

                return $shop;
            })
            ->filter(fn (Shop $shop) => $shop->distance_km <= $radius)
            ->sortBy('distance_km')
            ->take($limit)
            ->values();

        return response()->json([
            'data' => $shops->map(fn (Shop $shop) => $this->shopPayload($shop) + [
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                'distance_km' => round($shop->distance_km, 2),
            ]),
            'meta' => ['lat' => $lat, 'lng' => $lng, 'radius_km' => $radius, 'count' => $shops->count()],
        ]);
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return array<string,mixed>
     */
    private function shopPayload(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'locality' => $shop->locality,
            'pincode' => $shop->pincode,
            'status' => $shop->status,
            'product_count' => $shop->product_count ?? $shop->products()->where('status', Product::STATUS_PUBLISHED)->count(),
            'joined_at' => $shop->created_at?->toIso8601String(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
