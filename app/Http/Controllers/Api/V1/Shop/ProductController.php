<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('active_shop');

        $products = Product::ownedBy($shop->id)
            ->with(['images', 'colors', 'unit', 'subcategory.category'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('q'), fn ($q, $term) => $q->where('name', 'like', '%'.str_replace('%', '\\%', $term).'%'))
            ->latest('id')
            ->paginate((int) $request->input('per_page', 12));

        return ProductResource::collection($products)->response();
    }

    public function store(ProductRequest $request, ProductService $service): JsonResponse
    {
        $shop = $request->attributes->get('active_shop');

        $product = $service->save($shop, $request->productData());

        if ($request->hasFile('images')) {
            $service->addImages($product, $request->file('images'));
        }

        if ($request->input('status') === Product::STATUS_PUBLISHED) {
            $service->publish($product->refresh());
        }

        return response()->json(['data' => new ProductResource($product->refresh())], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function update(ProductRequest $request, Product $product, ProductService $service): JsonResponse
    {
        Gate::authorize('update', $product);

        $service->save($request->attributes->get('active_shop'), $request->productData(), $product);

        if ($request->hasFile('images')) {
            $service->addImages($product->refresh(), $request->file('images'));
        }

        if ($request->input('status') === Product::STATUS_PUBLISHED) {
            $service->publish($product->refresh());
        }

        return response()->json(['data' => new ProductResource($product->refresh())]);
    }

    public function destroy(Request $request, Product $product, ProductService $service): JsonResponse
    {
        Gate::authorize('delete', $product);

        $service->delete($product);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function uploadImages(Request $request, Product $product, ProductService $service): JsonResponse
    {
        Gate::authorize('manageImages', $product);

        $request->validate([
            'images' => ['required', 'array', 'max:'.config('marketplace.products.max_images')],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('marketplace.products.image_max_kb')],
        ]);

        $service->addImages($product, $request->file('images'));

        return response()->json([
            'data' => $product->refresh()->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url(), 'order' => $i->sort_order]),
        ], 201);
    }

    public function reorderImages(Request $request, Product $product, ProductService $service): JsonResponse
    {
        Gate::authorize('manageImages', $product);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $service->reorderImages($product, $data['order']);

        return response()->json([
            'data' => $product->refresh()->images->map(fn ($i) => ['id' => $i->id, 'order' => $i->sort_order]),
        ]);
    }

    public function deleteImage(Request $request, Product $product, ProductImage $image, ProductService $service): JsonResponse
    {
        Gate::authorize('manageImages', $product);
        abort_unless((int) $image->product_id === (int) $product->id, 403);

        $service->deleteImage($product, $image);

        return response()->json(['data' => ['deleted' => true]]);
    }
}
