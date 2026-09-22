<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id', 'name', 'name_key', 'slug', 'locality', 'pincode', 'address',
    'latitude', 'longitude', 'gst_number', 'secondary_phone', 'email', 'status',
    'status_changed_at',
])]
class Shop extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_SUSPENDED];

    protected function casts(): array
    {
        return [
            'status_changed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(ShopStatusEvent::class)->latest('id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canManage(): bool
    {
        return $this->isActive();
    }

    public function statusMessage(): ?string
    {
        return match ($this->status) {
            self::STATUS_INACTIVE => 'Your shop account is inactive. Please contact support.',
            self::STATUS_SUSPENDED => 'Your shop account is suspended. Please contact support.',
            default => null,
        };
    }

    /** Publicly visible shops only. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
