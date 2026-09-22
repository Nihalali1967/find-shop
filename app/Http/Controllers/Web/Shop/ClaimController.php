<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Models\ShopInvitation;
use App\Services\Otp\OnboardingGrantService;
use App\Services\Shops\ShopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClaimController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = ShopInvitation::where('claim_token_hash', hash('sha256', $token))->first();

        if (! $invitation || ! $invitation->isClaimable()) {
            return redirect()->route('shop.login')->withErrors(['claim' => 'This invitation is no longer valid.']);
        }

        if (! Auth::check()) {
            $request->session()->put('claim_token', $token);

            return redirect()->route('shop.login')->with('status', 'Verify the mobile number this invitation was sent to.');
        }

        if (Auth::user()->phone !== $invitation->intended_phone) {
            return view('shop.claim', ['invitation' => $invitation, 'mismatch' => true]);
        }

        return view('shop.claim', ['invitation' => $invitation, 'mismatch' => false]);
    }

    public function claim(
        Request $request,
        ShopService $shops,
        OnboardingGrantService $grants,
    ): RedirectResponse {
        $token = (string) $request->session()->pull('claim_token', $request->input('claim_token', ''));

        $invitation = ShopInvitation::where('claim_token_hash', hash('sha256', $token))
            ->lockForUpdate()
            ->first();

        if (! $invitation || ! $invitation->isClaimable()) {
            return redirect()->route('shop.login')->withErrors(['claim' => 'This invitation is no longer valid.']);
        }

        // Claim requires the same verified phone the invitation was addressed to.
        if ($request->user()->phone !== $invitation->intended_phone) {
            return redirect()->route('shop.after-login')->withErrors(['claim' => 'Sign in with the invited mobile number to claim this shop.']);
        }

        $grant = (string) $request->session()->get('shop_grant', '');

        if ($grant !== '') {
            $grants->redeem($grant, OnboardingGrantService::PURPOSE_SHOP_REGISTRATION);
            $request->session()->forget('shop_grant');
        }

        $profile = $invitation->profile ?? [];

        $shop = DB::transaction(function () use ($shops, $invitation, $profile, $request) {
            $shop = $shops->register($request->user(), [
                'name' => $invitation->name,
                'locality' => $profile['locality'] ?? '—',
                'pincode' => $profile['pincode'] ?? '000000',
                'address' => $profile['address'] ?? '—',
                'status' => $invitation->intended_status,
            ]);

            $invitation->forceFill(['claimed_at' => now(), 'shop_id' => $shop->id])->save();

            return $shop;
        });

        return redirect()->route('shop.after-login')->with('status', "{$shop->name} is ready.");
    }
}
