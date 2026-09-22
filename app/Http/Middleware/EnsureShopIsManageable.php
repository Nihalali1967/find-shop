<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopIsManageable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $shop = $user ? Shop::where('owner_id', $user->id)->first() : null;

        if (! $shop) {
            return response()->json(['message' => 'No shop is linked to this account.', 'code' => 'SHOP_MISSING'], 403);
        }

        if (! $shop->isActive()) {
            return response()->json([
                'message' => $shop->statusMessage() ?? 'This shop account is unavailable.',
                'code' => 'SHOP_'.strtoupper($shop->status),
            ], 403);
        }

        $request->attributes->set('active_shop', $shop);

        return $next($request);
    }
}
