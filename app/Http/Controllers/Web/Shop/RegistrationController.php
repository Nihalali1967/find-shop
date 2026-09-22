<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShopRegistrationRequest;
use App\Models\Shop;
use App\Services\Otp\OnboardingGrantService;
use App\Services\Shops\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function show(Request $request, OnboardingGrantService $grants): View|RedirectResponse
    {
        if (Shop::withTrashed()->where('owner_id', $request->user()->id)->exists()) {
            return redirect()->route('shop.after-login');
        }

        // Issue (or refresh) the verified onboarding grant used at commit time.
        if (! $request->session()->has('shop_grant')
            || ! $grants->find((string) $request->session()->get('shop_grant'), OnboardingGrantService::PURPOSE_SHOP_REGISTRATION)) {
            $grant = $grants->issue($request->user(), OnboardingGrantService::PURPOSE_SHOP_REGISTRATION);
            $request->session()->put('shop_grant', $grant['token']);
        }

        return view('shop.register', [
            'user' => $request->user(),
        ]);
    }

    public function checkName(Request $request, ShopService $shops): JsonResponse
    {
        $name = (string) $request->input('name');
        $key = 'shop-name-check:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json(['message' => 'Too many checks. Slow down.'], 429);
        }

        RateLimiter::hit($key, 60);

        if (trim($name) === '') {
            return response()->json(['data' => ['available' => false, 'message' => 'Enter a shop name.']]);
        }

        $available = $shops->isNameAvailable($name);

        return response()->json([
            'data' => [
                'available' => $available,
                'message' => $available ? 'That name is available.' : 'That name is already taken.',
            ],
        ]);
    }

    public function store(
        ShopRegistrationRequest $request,
        ShopService $shops,
        OnboardingGrantService $grants,
    ): RedirectResponse {
        $token = (string) $request->session()->get('shop_grant', '');

        // Consume the single-use verified onboarding grant.
        $grants->redeem($token, OnboardingGrantService::PURPOSE_SHOP_REGISTRATION);
        $request->session()->forget('shop_grant');

        $shop = $shops->register($request->user(), $request->validated());

        return redirect()->route('shop.dashboard')->with('status', "Welcome, {$shop->name} is now live.");
    }
}
