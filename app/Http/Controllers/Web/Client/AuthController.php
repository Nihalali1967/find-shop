<?php

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Support\NameNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        return view('client.auth.login', [
            'purpose' => $request->routeIs('shop.login') ? 'shop_login' : 'client_login',
            'shopPortal' => $request->routeIs('shop.login'),
        ]);
    }

    public function requestOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['required', 'in:client_login,shop_login'],
        ]);

        $challenge = $otp->request(
            $data['phone'],
            $data['purpose'],
            $request->ip(),
            (string) $request->userAgent(),
        );

        $request->session()->put('otp', [
            'challenge_id' => $challenge->challenge_id,
            'phone' => $challenge->phone,
            'purpose' => $challenge->purpose,
        ]);

        return redirect()->route($data['purpose'] === 'shop_login' ? 'shop.otp.verify' : 'client.otp.verify');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $state = $request->session()->get('otp');

        if (! $state) {
            return redirect()->route($request->routeIs('shop.otp.verify') ? 'shop.login' : 'client.login');
        }

        return view('client.auth.verify', [
            'phone' => $state['phone'],
            'shopPortal' => $state['purpose'] === 'shop_login',
            'resendIn' => max(0, (int) config('marketplace.otp.resend_seconds')),
        ]);
    }

    public function verifyOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        $state = $request->session()->get('otp');

        if (! $state) {
            return redirect()->route('client.login')->withErrors(['code' => 'Your session expired. Request a new code.']);
        }

        $challenge = $otp->verify($state['challenge_id'], $data['code'], $state['purpose']);

        $request->session()->forget('otp');

        $user = User::firstOrCreate(
            ['phone' => $challenge->phone],
            ['phone_verified_at' => now()],
        );

        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        if ($state['purpose'] === 'shop_login') {
            return redirect()->route('shop.after-login');
        }

        $intent = $request->session()->pull('chat_intent');

        if ($intent) {
            $product = Product::query()->visible()->find($intent);

            if ($product) {
                return redirect()->route('client.chat.start', $product);
            }
        }

        return redirect()->intended(route('client.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.home');
    }

    public function resendOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $challenge = $otp->latestActive(
            NameNormalizer::phone((string) $request->input('phone')),
            (string) $request->input('purpose', 'client_login'),
        );

        if ($challenge) {
            $fresh = $otp->request($challenge->phone, $challenge->purpose, $request->ip(), (string) $request->userAgent());

            $request->session()->put('otp', [
                'challenge_id' => $fresh->challenge_id,
                'phone' => $fresh->phone,
                'purpose' => $fresh->purpose,
            ]);
        }

        return back()->with('status', 'A new code has been sent.');
    }
}
