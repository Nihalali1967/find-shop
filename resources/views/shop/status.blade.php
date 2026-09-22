@extends('layouts.shop')

@section('title', 'Shop status')
@section('nav', 'status')
@section('page-title', 'Shop status')

@section('content')
    <div class="status-hero is-{{ $shop->status }} mb-2">
        <div class="flex between center wrap-gap">
            <div>
                <span class="eyebrow-soft">Current status</span>
                <h2 class="mt-0">{{ ucfirst($shop->status) }}</h2>
                <p class="mb-0">
                    @if ($shop->status === 'active')
                        Your shop is active. Buyers can find and contact you.
                    @elseif ($shop->trashed())
                        This shop is unavailable. Historical conversations are retained but nothing new can be sent.
                    @else
                        {{ $shop->statusMessage() }}
                    @endif
                </p>
            </div>
            <x-status-badge :status="$shop->trashed() ? 'deleted' : $shop->status" />
        </div>

        @if (! $shop->isActive() && ! $shop->trashed())
            <div class="alert alert-warn mt-2">
                <div>
                    While restricted, your published products are hidden from the catalog, and buyers cannot send new messages.
                    Existing history stays readable. No further action happens automatically — contact support to restore access.
                </div>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Status history</h2>
            <a href="{{ route('shop.login') }}" class="btn btn-ghost btn-sm">Sign out and verify again</a>
        </div>
        <div class="card-body">
            @if ($events->isEmpty())
                <p class="muted mb-0">No status changes recorded yet.</p>
            @else
                <ul class="timeline">
                    @foreach ($events as $event)
                        <li>
                            <div class="flex between wrap-gap">
                                <strong>
                                    {{ $event->from_status ? ucfirst($event->from_status).' → ' : '' }}{{ ucfirst($event->to_status) }}
                                </strong>
                                <span class="muted" style="font-size:.82rem">{{ $event->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            @if ($event->reason)
                                <div class="muted" style="font-size:.87rem">{{ $event->reason }}</div>
                            @endif
                            <div class="muted" style="font-size:.76rem">Changed by {{ $event->actor_type ?? 'system' }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid grid-2 mt-3">
        <div class="card card-pad">
            <h3>Need help?</h3>
            <p class="muted mb-0">Share your shop name and registered number with support so they can locate the account.</p>
        </div>
        <div class="card card-pad">
            <h3>Your shop</h3>
            <ul class="spec-list" style="margin:0">
                <li><span class="k">Name</span><span>{{ $shop->name }}</span></li>
                <li><span class="k">Locality</span><span>{{ $shop->locality }}</span></li>
                <li><span class="k">Pincode</span><span>{{ $shop->pincode }}</span></li>
            </ul>
        </div>
    </div>

    <form method="POST" action="{{ route('shop.logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-ghost">Log out</button>
    </form>
@endsection
