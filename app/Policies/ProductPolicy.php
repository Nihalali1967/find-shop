<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;

class ProductPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    public function view(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $product->isPublished();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product) && $this->shopIsActive($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product) && $this->shopIsActive($user);
    }

    public function manageImages(User $user, Product $product): bool
    {
        return $this->owns($user, $product) && $this->shopIsActive($user);
    }

    protected function owns(User $user, Product $product): bool
    {
        return (int) $product->shop?->owner_id === (int) $user->id;
    }

    protected function shopIsActive(User $user): bool
    {
        $shop = Shop::where('owner_id', $user->id)->first();

        return (bool) $shop?->isActive();
    }
}
