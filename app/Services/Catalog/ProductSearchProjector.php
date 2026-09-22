<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductSearchDocument;
use App\Support\NameNormalizer;

class ProductSearchProjector
{
    public function sync(Product $product): ProductSearchDocument
    {
        $product->loadMissing(['subcategory.category', 'colors', 'unit']);

        $fieldMap = [
            'name' => NameNormalizer::key($product->name),
            'title' => NameNormalizer::key($product->title),
            'category' => NameNormalizer::key($product->subcategory?->category?->name),
            'subcategory' => NameNormalizer::key($product->subcategory?->name),
            'colors' => NameNormalizer::key($product->colors->pluck('name')->implode(' ')),
            'unit' => NameNormalizer::key(trim(($product->unit?->name ?? '').' '.($product->custom_unit ?? ''))),
            'description' => NameNormalizer::key($product->description),
        ];

        return ProductSearchDocument::updateOrCreate(
            ['product_id' => $product->id],
            [
                'document' => trim(implode(' ', array_filter($fieldMap))),
                'field_map' => $fieldMap,
            ],
        );
    }

    public function forget(Product $product): void
    {
        ProductSearchDocument::where('product_id', $product->id)->delete();
    }
}
