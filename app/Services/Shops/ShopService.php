<?php

namespace App\Services\Shops;

use App\Exceptions\MarketplaceException;
use App\Models\Admin;
use App\Models\Shop;
use App\Models\ShopStatusEvent;
use App\Models\User;
use App\Support\NameNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ShopService
{
    public function isNameAvailable(string $name, ?int $exceptShopId = null): bool
    {
        $key = NameNormalizer::key($name);

        if ($key === '') {
            return false;
        }

        $takenByShop = Shop::withTrashed()
            ->where('name_key', $key)
            ->when($exceptShopId, fn ($q) => $q->where('id', '!=', $exceptShopId))
            ->exists();

        if ($takenByShop) {
            return false;
        }

        return ! \App\Models\ShopInvitation::query()
            ->where('name_key', $key)
            ->whereNull('claimed_at')
            ->exists();
    }

    public function generateSlug(string $name): string
    {
        $base = NameNormalizer::slug($name);
        $slug = $base;
        $i = 1;

        while (Shop::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * Create the shop for a verified user inside a transaction.
     *
     * @param  array{name:string,locality:string,pincode:string,address:string,gst_number?:?string,secondary_phone?:?string,email?:?string,status?:string}  $data
     */
    public function register(User $user, array $data): Shop
    {
        $name = NameNormalizer::normalize($data['name']);
        $key = NameNormalizer::key($name);

        try {
            return DB::transaction(function () use ($user, $data, $name, $key) {
                $existing = Shop::withTrashed()->where('owner_id', $user->id)->lockForUpdate()->first();

                if ($existing) {
                    throw MarketplaceException::conflict('This account already has a shop.', 'SHOP_EXISTS');
                }

                if (Shop::withTrashed()->where('name_key', $key)->lockForUpdate()->exists()) {
                    throw MarketplaceException::conflict('That shop name is already taken.', 'SHOP_NAME_TAKEN');
                }

                $status = $data['status'] ?? Shop::STATUS_ACTIVE;

                $shop = Shop::create([
                    'owner_id' => $user->id,
                    'name' => $name,
                    'name_key' => $key,
                    'slug' => $this->generateSlug($name),
                    'locality' => NameNormalizer::normalize($data['locality']),
                    'pincode' => $data['pincode'],
                    'address' => NameNormalizer::normalize($data['address']),
                    'gst_number' => $data['gst_number'] ?? null,
                    'secondary_phone' => isset($data['secondary_phone']) ? NameNormalizer::phone($data['secondary_phone']) : null,
                    'email' => $data['email'] ?? null,
                    'status' => $status,
                    'status_changed_at' => now(),
                ]);

                ShopStatusEvent::create([
                    'shop_id' => $shop->id,
                    'from_status' => null,
                    'to_status' => $status,
                    'reason' => 'Shop registered',
                    'actor_type' => 'user',
                    'actor_id' => $user->id,
                ]);

                return $shop;
            });
        } catch (QueryException $e) {
            // Concurrent duplicate registration loses the race -> field-level conflict.
            if ($this->isUniqueViolation($e)) {
                throw MarketplaceException::conflict('That shop name is already taken.', 'SHOP_NAME_TAKEN');
            }

            throw $e;
        }
    }

    public function changeStatus(Shop $shop, string $status, ?string $reason = null, ?Admin $actor = null): Shop
    {
        if (! in_array($status, Shop::STATUSES, true)) {
            throw MarketplaceException::invalid('Unknown shop status.');
        }

        if ($shop->status === $status) {
            return $shop;
        }

        return DB::transaction(function () use ($shop, $status, $reason, $actor) {
            $from = $shop->status;

            $shop->forceFill([
                'status' => $status,
                'status_changed_at' => now(),
            ])->save();

            ShopStatusEvent::create([
                'shop_id' => $shop->id,
                'from_status' => $from,
                'to_status' => $status,
                'reason' => $reason,
                'actor_type' => $actor ? 'admin' : 'system',
                'actor_id' => $actor?->id,
            ]);

            // Revoke API tokens for the owner when a shop leaves active state.
            if ($status !== Shop::STATUS_ACTIVE && $shop->owner) {
                $shop->owner->tokens()->delete();
            }

            return $shop->refresh();
        });
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true)
            || str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
