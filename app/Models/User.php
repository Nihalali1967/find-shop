<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['phone', 'name', 'email', 'password', 'phone_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory;

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class, 'owner_id');
    }

    public function clientConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'client_id');
    }

    public function appNotifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'recipient');
    }

    public function ownsShop(): bool
    {
        return $this->shop()->exists();
    }

    public function hasActiveShop(): bool
    {
        return (bool) $this->shop?->isActive();
    }
}
