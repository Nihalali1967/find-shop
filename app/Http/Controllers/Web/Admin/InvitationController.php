<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopInvitation;
use App\Services\Audit\AuditLogger;
use App\Services\Shops\ShopService;
use App\Support\NameNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function index(): View
    {
        return view('admin.shops.invitations', [
            'invitations' => ShopInvitation::with('creator', 'shop')->latest('id')->paginate(15),
            'intendedStatuses' => Shop::STATUSES,
        ]);
    }

    public function store(Request $request, ShopService $shops): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'intended_phone' => ['required', 'string', 'max:20'],
            'locality' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'address' => ['required', 'string', 'max:400'],
            'intended_status' => ['required', 'in:active,inactive'],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        if (! $shops->isNameAvailable($data['name'])) {
            throw ValidationException::withMessages(['name' => 'That shop name is already taken or invited.']);
        }

        $token = Str::random(64);

        $invitation = ShopInvitation::create([
            'name' => NameNormalizer::normalize($data['name']),
            'name_key' => NameNormalizer::key($data['name']),
            'intended_phone' => NameNormalizer::phone($data['intended_phone']),
            'claim_token_hash' => hash('sha256', $token),
            'profile' => [
                'locality' => $data['locality'],
                'pincode' => $data['pincode'],
                'address' => $data['address'],
            ],
            'intended_status' => $data['intended_status'],
            'created_by_admin_id' => $request->user('admin')->id,
            'expires_at' => now()->addDays((int) $data['expires_in_days']),
        ]);

        AuditLogger::log('shop_invitation.created', subject: $invitation, actor: $request->user('admin'), ip: $request->ip());

        return back()
            ->with('status', 'Invitation created. Share this one-time claim link with the owner.')
            ->with('claim_url', route('shop.claim.show', $token));
    }

    public function destroy(Request $request, ShopInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->claimed_at !== null, 422, 'A claimed invitation cannot be revoked.');

        AuditLogger::log('shop_invitation.revoked', subject: $invitation, actor: $request->user('admin'), ip: $request->ip());
        $invitation->delete();

        return back()->with('status', 'Invitation revoked.');
    }
}
