<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'requires_custom', 'is_active', 'sort_order'])]
class Unit extends Model
{
    public const OTHERS = 'others';

    protected function casts(): array
    {
        return [
            'requires_custom' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
