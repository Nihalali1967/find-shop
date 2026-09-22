<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $this->authorize('manageImages', $product);

        $request->validate([
            'images' => ['required', 'array', 'max:'.config('marketplace.products.max_images')],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('marketplace.products.image_max_kb')],
        ]);

        $service->addImages($product, $request->file('images'));

        return back()->with('status', 'Images uploaded.');
    }

    public function reorder(Request $request, Product $product, ProductService $service): RedirectResponse
    {
        $this->authorize('manageImages', $product);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $service->reorderImages($product, $data['order']);

        return back()->with('status', 'Image order updated. The first image is the cover.');
    }

    public function destroy(Request $request, Product $product, ProductImage $image, ProductService $service): RedirectResponse
    {
        $this->authorize('manageImages', $product);

        // A nested id that is not part of this product is never accepted.
        abort_unless((int) $image->product_id === (int) $product->id, 403);

        $service->deleteImage($product, $image);

        return back()->with('status', 'Image removed.');
    }
}
