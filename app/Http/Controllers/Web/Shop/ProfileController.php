<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Shop\Concerns\ResolvesShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use ResolvesShop;

    public function edit(Request $request): View
    {
        return view('shop.profile', ['shop' => $this->currentShop($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = $this->currentShop($request);

        $data = $request->validate([
            'locality' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'address' => ['required', 'string', 'max:400'],
            'gst_number' => ['nullable', 'string', 'max:15'],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        // Owner id is never accepted from input; contact edits cannot reassign the shop.
        $shop->fill($data)->save();

        return back()->with('status', 'Shop profile updated.');
    }
}
