<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('shop.login');
        }

        $shop = Shop::withTrashed()->where('owner_id', $user->id)->first();

        if (! $shop) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No shop is linked to this account.', 'code' => 'SHOP_MISSING'], 403)
                : redirect()->route('shop.register');
        }

        if (! $shop->isActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $shop->statusMessage() ?? 'This shop account is unavailable.',
                    'code' => 'SHOP_'.strtoupper($shop->status),
                ], 403);
            }

            return redirect()->route('shop.status');
        }

        $request->attributes->set('active_shop', $shop);

        return $next($request);
    }
}
