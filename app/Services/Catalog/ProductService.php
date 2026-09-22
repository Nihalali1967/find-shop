<?php

namespace App\Services\Catalog;

use App\Exceptions\MarketplaceException;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Services\Media\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private readonly ImageProcessor $images,
        private readonly ProductSearchProjector $projector,
    ) {}

    /**
     * @param  array<string,mixed>  $data  normalized (paise) attribute array
     */
    public function save(Shop $shop, array $data, ?Product $product = null): Product
    {
        $this->assertTaxonomy($data['subcategory_id'] ?? null);
        $this->assertUnit($data['unit_id'] ?? null, $data['custom_unit'] ?? null);
        $this->assertPricing($data);

        $product ??= new Product(['shop_id' => $shop->id]);
        $product->fill($data);

        if ($product->exists && $product->shop_id !== $shop->id) {
            throw MarketplaceException::invalid('You cannot edit this product.', 'FORBIDDEN');
        }

        if ($product->status === Product::STATUS_PUBLISHED) {
            $product->published_at ??= now();
        }

        DB::transaction(function () use ($product, $data) {
            $product->save();

            if (isset($data['color_ids'])) {
                $product->colors()->sync(array_filter((array) $data['color_ids']));
            }
        });

        $this->projector->sync($product->refresh());

        return $product;
    }

    public function publish(Product $product): Product
    {
        $hasImages = $product->images()->exists();

        if (! Product::isComplete($product->toArray(), $hasImages)) {
            throw MarketplaceException::invalid(
                'Add a cover image and complete all required fields before publishing.',
                'PRODUCT_INCOMPLETE'
            );
        }

        $product->forceFill(['status' => Product::STATUS_PUBLISHED, 'published_at' => now()])->save();

        return $product->refresh();
    }

    /**
     * @param  array<int,UploadedFile>  $files
     */
    public function addImages(Product $product, array $files): void
    {
        $max = (int) config('marketplace.products.max_images');
        $existing = $product->images()->count();

        if ($existing + count($files) > $max) {
            throw MarketplaceException::invalid("You can keep up to {$max} images per product.", 'TOO_MANY_IMAGES');
        }

        $order = (int) $product->images()->max('sort_order');

        foreach ($files as $file) {
            $stored = $this->images->store($file);
            $order++;

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $stored['path'],
                'thumb_path' => $stored['thumb_path'],
                'sort_order' => $order,
            ]);
        }
    }

    /**
     * @param  array<int,int>  $orderedIds  full ordered image id list; the first becomes the cover
     */
    public function reorderImages(Product $product, array $orderedIds): void
    {
        $owned = $product->images()->pluck('id')->all();

        if (count(array_diff($owned, $orderedIds)) > 0 || count($owned) !== count($orderedIds)) {
            throw MarketplaceException::invalid('The image order list must include every image exactly once.', 'IMAGE_ORDER_INVALID');
        }

        DB::transaction(function () use ($product, $orderedIds) {
            foreach (array_values($orderedIds) as $index => $id) {
                ProductImage::where('product_id', $product->id)
                    ->where('id', $id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function deleteImage(Product $product, ProductImage $image): void
    {
        if ((int) $image->product_id !== (int) $product->id) {
            throw new MarketplaceException('FORBIDDEN', 'That image does not belong to this product.', 403);
        }

        if ($product->isPublished() && $product->images()->count() <= 1) {
            throw MarketplaceException::invalid('A published product needs at least one image.', 'COVER_REQUIRED');
        }

        $paths = [$image->path, $image->thumb_path];
        $image->delete();

        foreach ($paths as $path) {
            $this->images->delete($path);
        }
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->searchDocument()->delete();
            $product->delete();
        });
    }

    protected function assertTaxonomy(?int $subcategoryId): void
    {
        if (! $subcategoryId) {
            throw MarketplaceException::invalid('Choose a category and subcategory.', 'TAXONOMY_REQUIRED');
        }

        $subcategory = Subcategory::query()->with('category')->find($subcategoryId);

        if (! $subcategory || ! $subcategory->category || $subcategory->category->status !== 'active') {
            throw MarketplaceException::invalid('Choose an active category and subcategory.', 'TAXONOMY_INVALID');
        }
    }

    protected function assertUnit(?int $unitId, ?string $customUnit): void
    {
        $unit = $unitId ? Unit::find($unitId) : null;

        if (! $unit || ! $unit->is_active) {
            throw MarketplaceException::invalid('Choose an active unit.', 'UNIT_INVALID');
        }

        if ($unit->requires_custom && blank($customUnit)) {
            throw MarketplaceException::invalid('Describe the custom unit.', 'CUSTOM_UNIT_REQUIRED');
        }
    }

    protected function assertPricing(array $data): void
    {
        $price = (int) ($data['price_paise'] ?? 0);

        if ($price <= 0) {
            throw MarketplaceException::invalid('Enter a price greater than zero.', 'PRICE_INVALID');
        }

        $offer = $data['offer_price_paise'] ?? null;

        if ($offer !== null && ((int) $offer <= 0 || (int) $offer >= $price)) {
            throw MarketplaceException::invalid('The offer price must be lower than the regular price.', 'OFFER_INVALID');
        }
    }
}
