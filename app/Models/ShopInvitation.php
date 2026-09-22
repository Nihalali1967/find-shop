<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name', 'name_key', 'intended_phone', 'claim_token_hash', 'profile',
    'intended_status', 'created_by_admin_id', 'expires_at', 'claimed_at', 'shop_id',
])]
class ShopInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'profile' => 'array',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function isClaimable(): bool
    {
        return $this->claimed_at === null && $this->expires_at->isFuture();
    }
}
