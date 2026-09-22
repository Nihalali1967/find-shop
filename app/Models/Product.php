<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'shop_id', 'subcategory_id', 'unit_id', 'name', 'title', 'description',
    'custom_unit', 'unit_count', 'price_paise', 'offer_price_paise', 'status', 'published_at',
])]
class Product extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected function casts(): array
    {
        return [
            'unit_count' => 'decimal:3',
            'price_paise' => 'integer',
            'offer_price_paise' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'product_colors');
    }

    public function searchDocument(): HasOne
    {
        return $this->hasOne(ProductSearchDocument::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function coverImage(): ?ProductImage
    {
        return $this->images->first();
    }

    public function category(): ?Category
    {
        return $this->subcategory?->category;
    }

    public function effectivePricePaise(): int
    {
        return $this->offer_price_paise ?? $this->price_paise;
    }

    public function hasOffer(): bool
    {
        return $this->offer_price_paise !== null
            && $this->offer_price_paise > 0
            && $this->offer_price_paise < $this->price_paise;
    }

    public function unitLabel(): string
    {
        if ($this->unit?->code === Unit::OTHERS) {
            return $this->custom_unit ?: 'unit';
        }

        return $this->unit?->name ?? 'unit';
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public static function isComplete(array $data, bool $hasImages): bool
    {
        return $hasImages
            && ! empty($data['name'])
            && ! empty($data['title'])
            && ! empty($data['description'])
            && ! empty($data['subcategory_id'])
            && ! empty($data['unit_id'])
            && ! empty($data['price_paise']);
    }

    /**
     * Publicly visible products: published, on an active shop and active taxonomy.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('products.status', self::STATUS_PUBLISHED)
            ->whereHas('shop', fn (Builder $q) => $q->where('status', Shop::STATUS_ACTIVE))
            ->whereHas('subcategory', fn (Builder $q) => $q->where('status', 'active')
                ->whereHas('category', fn (Builder $c) => $c->where('status', 'active')));
    }

    public function scopeOwnedBy(Builder $query, int $shopId): Builder
    {
        return $query->where('products.shop_id', $shopId);
    }
}
