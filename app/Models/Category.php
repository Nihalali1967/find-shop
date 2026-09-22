<?php

namespace App\Models;

use App\Support\NameNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_key', 'slug', 'status', 'sort_order'])]
class Category extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            $category->name_key = NameNormalizer::key($category->name);
            $category->slug ??= NameNormalizer::slug($category->name);
        });
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class)->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasManyThrough(Product::class, Subcategory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
