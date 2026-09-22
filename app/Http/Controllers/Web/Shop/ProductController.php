<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Shop\Concerns\ResolvesShop;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Catalog\ProductService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use ResolvesShop;

    public function index(Request $request): View
    {
        $shop = $this->currentShop($request);

        $products = Product::ownedBy($shop->id)
            ->with(['images', 'colors', 'unit', 'subcategory.category'])
            ->when($request->input('q'), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%')
                        ->orWhere('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%');
                });
            })
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->integer('category_id'), fn ($query, $id) => $query->whereHas('subcategory', fn ($s) => $s->where('category_id', $id)))
            ->latest('id')
            ->paginate(12);

        return view('shop.products.index', [
            'shop' => $shop,
            'products' => $products,
            'categories' => Category::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('shop.products.form', [
            'shop' => $this->currentShop($request),
            'product' => new Product(['unit_count' => 1, 'status' => Product::STATUS_DRAFT]),
            'categories' => $this->categories(),
            'units' => Unit::query()->active()->orderBy('sort_order')->get(),
            'colors' => Color::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(ProductRequest $request, ProductService $service): RedirectResponse
    {
        $shop = $this->currentShop($request);
        $product = $service->save($shop, $request->productData());

        if ($request->hasFile('images')) {
            $service->addImages($product, $request->file('images'));
        }

        if ($request->input('status') === Product::STATUS_PUBLISHED) {
            $service->publish($product->refresh());
        }

        return redirect()->route('shop.products.index')->with('status', 'Product saved.');
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorize('update', $product);

        return view('shop.products.form', [
            'shop' => $this->currentShop($request),
            'product' => $product->load(['images', 'colors']),
            'categories' => $this->categories(),
            'units' => Unit::query()->active()->orderBy('sort_order')->get(),
            'colors' => Color::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $this->authorize('update', $product);

        $service->save($this->currentShop($request), $request->productData(), $product);

        if ($request->hasFile('images')) {
            $service->addImages($product->refresh(), $request->file('images'));
        }

        if ($request->input('status') === Product::STATUS_PUBLISHED) {
            $service->publish($product->refresh());
        }

        return redirect()->route('shop.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $this->authorize('delete', $product);

        $service->delete($product);

        return redirect()->route('shop.products.index')->with('status', 'Product deleted. Chat history is preserved.');
    }

    /**
     * @return \Illuminate\Support\Collection<int,Category>
     */
    protected function categories()
    {
        return Category::query()->active()
            ->with(['subcategories' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get();
    }
}
