<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function show(Request $request): View
    {
        $shop = Shop::withTrashed()->where('owner_id', $request->user()->id)->firstOrFail();

        return view('shop.status', [
            'shop' => $shop,
            'events' => $shop->statusEvents()->limit(10)->get(),
        ]);
    }
}
