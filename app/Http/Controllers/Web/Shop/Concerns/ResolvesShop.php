<?php

namespace App\Http\Controllers\Web\Shop\Concerns;

use App\Models\Shop;
use Illuminate\Http\Request;

trait ResolvesShop
{
    protected function currentShop(Request $request): Shop
    {
        return $request->attributes->get('active_shop')
            ?? Shop::where('owner_id', $request->user()->id)->firstOrFail();
    }

    protected function shopOwnerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
