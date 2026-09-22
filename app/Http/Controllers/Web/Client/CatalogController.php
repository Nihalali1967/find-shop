<?php

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request, ProductSearch $search): View
    {
        $filters = $this->filters($request);
        $products = $search->query($filters, perPage: (int) $request->input('per_page', 12));
        $parsed = $search->parseQuery($filters['q'] ?? null);

        return view('client.catalog.index', [
            'products' => $products,
            'filters' => $filters,
            'parsed' => $parsed,
            'categories' => Category::query()->active()->with(['subcategories' => fn ($q) => $q->active()])->orderBy('sort_order')->get(),
            'colors' => Color::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->status === Product::STATUS_PUBLISHED, 404);

        $product->load(['shop', 'subcategory.category', 'unit', 'colors', 'images']);

        abort_unless($product->shop && $product->shop->isActive(), 404);
        abort_unless($product->subcategory?->status === 'active' && $product->subcategory?->category?->status === 'active', 404);

        $suggestions = Product::query()->visible()
            ->where('products.id', '!=', $product->id)
            ->where('products.subcategory_id', $product->subcategory_id)
            ->with(['shop', 'images', 'unit', 'colors', 'subcategory.category'])
            ->limit(4)
            ->get()
            ->each(fn (Product $p) => $p->setAttribute('effective_price_paise', $p->effectivePricePaise()));

        return view('client.catalog.show', [
            'product' => $product,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    protected function filters(Request $request): array
    {
        $min = $request->input('min_price');
        $max = $request->input('max_price');

        return [
            'q' => $request->input('q'),
            'category_id' => $request->integer('category_id') ?: null,
            'subcategory_id' => $request->integer('subcategory_id') ?: null,
            'colors' => array_filter((array) $request->input('colors', [])),
            // Web inputs accept rupees; the query layer works in paise.
            'min_price' => $min !== null && $min !== '' ? Money::toPaise($min) : null,
            'max_price' => $max !== null && $max !== '' ? Money::toPaise($max) : null,
            'sort' => $request->input('sort'),
        ];
    }
}
