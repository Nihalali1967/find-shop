<?php

namespace App\Models;

use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'name_key', 'slug', 'status', 'sort_order'])]
class Subcategory extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Subcategory $subcategory) {
            $subcategory->name_key = NameNormalizer::key($subcategory->name);
            $subcategory->slug ??= NameNormalizer::slug($subcategory->name);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
