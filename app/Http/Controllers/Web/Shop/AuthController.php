<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\Otp\OnboardingGrantService;
use App\Services\Otp\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('shop.auth.login');
    }

    public function requestOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $challenge = $otp->request(
            $data['phone'],
            'shop_login',
            $request->ip(),
            (string) $request->userAgent(),
        );

        $request->session()->put('shop_otp', [
            'challenge_id' => $challenge->challenge_id,
            'phone' => $challenge->phone,
        ]);

        return redirect()->route('shop.otp.verify');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $state = $request->session()->get('shop_otp');

        if (! $state) {
            return redirect()->route('shop.login');
        }

        return view('shop.auth.verify', [
            'phone' => $state['phone'],
            'resendIn' => max(0, (int) config('marketplace.otp.resend_seconds')),
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otp, OnboardingGrantService $grants): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $state = $request->session()->get('shop_otp');

        if (! $state) {
            return redirect()->route('shop.login')->withErrors(['code' => 'Your session expired. Request a new code.']);
        }

        $challenge = $otp->verify($state['challenge_id'], $data['code'], 'shop_login');

        $request->session()->forget('shop_otp');

        $user = User::firstOrCreate(['phone' => $challenge->phone], ['phone_verified_at' => now()]);

        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Bind a short-lived onboarding grant to the verified number.
        $grant = $grants->issue($user, OnboardingGrantService::PURPOSE_SHOP_REGISTRATION);
        $request->session()->put('shop_grant', $grant['token']);

        return redirect()->route('shop.after-login');
    }

    public function afterLogin(Request $request): RedirectResponse
    {
        $shop = Shop::withTrashed()->where('owner_id', $request->user()->id)->first();

        if (! $shop) {
            return redirect()->route('shop.register');
        }

        return $shop->isActive()
            ? redirect()->route('shop.dashboard')
            : redirect()->route('shop.status');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.login');
    }
}
