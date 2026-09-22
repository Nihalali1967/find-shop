@extends('layouts.public')

@section('title', 'Claim your shop')

@section('content')
    <div class="wrap" style="padding:48px 0 70px;max-width:720px">
        <span class="eyebrow-soft">Shop invitation</span>
        <h1>Claim “{{ $invitation->name }}”</h1>

        @if ($mismatch)
            <div class="alert alert-warn">
                <div>
                    You are signed in as <strong>{{ auth()->user()->phone }}</strong>, but this invitation was issued to
                    <strong>+{{ ltrim(substr($invitation->intended_phone, 1), '') }}</strong>.
                    Sign in with the invited number to claim the shop. Ownership is never transferred silently.
                </div>
            </div>
            <div class="btn-row mt-2">
                <a href="{{ route('shop.login') }}" class="btn btn-primary">Sign in with the invited number</a>
                <a href="{{ route('client.home') }}" class="btn btn-ghost">Back to catalog</a>
            </div>
        @else
            <div class="card card-pad">
                <ul class="spec-list" style="margin-top:0">
                    <li><span class="k">Shop name</span><span>{{ $invitation->name }}</span></li>
                    <li><span class="k">Invited number</span><span>{{ $invitation->intended_phone }}</span></li>
                    <li><span class="k">Locality</span><span>{{ $invitation->profile['locality'] ?? '—' }}</span></li>
                    <li><span class="k">Pincode</span><span>{{ $invitation->profile['pincode'] ?? '—' }}</span></li>
                    <li><span class="k">Intended status</span><span><x-status-badge :status="$invitation->intended_status" /></span></li>
                    <li><span class="k">Expires</span><span>{{ $invitation->expires_at->format('d M Y, H:i') }}</span></li>
                </ul>

                <div class="alert alert-info">
                    <div>Claiming creates your shop with this validated profile. You can refine address and contact details afterwards.</div>
                </div>

                <form method="POST" action="{{ route('shop.claim.submit') }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="claim_token" value="{{ request()->route('token') }}">
                    <button type="submit" class="btn btn-primary btn-lg">Claim this shop</button>
                </form>
            </div>
        @endif
    </div>
@endsection
