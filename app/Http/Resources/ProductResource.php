<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['shop', 'subcategory.category', 'unit', 'colors', 'images']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'price_paise' => $this->price_paise,
            'offer_price_paise' => $this->offer_price_paise,
            'effective_price_paise' => $this->effectivePricePaise(),
            'unit' => [
                'id' => $this->unit?->id,
                'name' => $this->unit?->name,
                'code' => $this->unit?->code,
                'custom' => $this->custom_unit,
            ],
            'unit_count' => (string) $this->unit_count,
            'category' => [
                'id' => $this->subcategory?->category?->id,
                'name' => $this->subcategory?->category?->name,
            ],
            'subcategory' => [
                'id' => $this->subcategory?->id,
                'name' => $this->subcategory?->name,
            ],
            'colors' => $this->colors->map(fn ($color) => [
                'id' => $color->id,
                'name' => $color->name,
                'hex' => $color->hex,
                'multicolor' => (bool) $color->is_multicolor,
            ])->values(),
            'images' => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url(),
                'thumb_url' => $image->thumbUrl(),
                'order' => $image->sort_order,
            ])->values(),
            'shop' => $this->whenLoaded('shop', fn () => [
                'id' => $this->shop?->id,
                'name' => $this->shop?->name,
                'locality' => $this->shop?->locality,
                'pincode' => $this->shop?->pincode,
                'status' => $this->shop?->status,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
