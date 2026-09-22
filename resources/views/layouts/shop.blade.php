@php
    $navShop = auth()->user()?->shop;
    $nav = trim($__env->yieldContent('nav')) ?: 'dashboard';
    $unreadNotifications = $navShop
        ? app(\App\Services\Notifications\NotificationService::class)->unreadCount('shop', $navShop->id)
        : 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shop panel') · {{ config('marketplace.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/marketplace.css') }}">
    @stack('head')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<div class="panel">
    <aside class="panel-side">
        <a class="brand" href="{{ route('shop.dashboard') }}">
            <span class="mark" aria-hidden="true">S</span>
            <span>{{ $navShop?->name ?? 'Shop panel' }}</span>
        </a>

        <nav class="panel-nav" aria-label="Shop">
            <a href="{{ route('shop.dashboard') }}" class="{{ $nav === 'dashboard' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">▤</span> Dashboard
            </a>
            <a href="{{ route('shop.products.index') }}" class="{{ $nav === 'products' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">▦</span> Products
            </a>
            <a href="{{ route('shop.chat.index') }}" class="{{ $nav === 'chat' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">✉</span> Chat
            </a>
            <a href="{{ route('shop.notifications.index') }}" class="{{ $nav === 'notifications' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">◔</span> Notifications
                @if ($unreadNotifications)
                    <span class="pill">{{ $unreadNotifications }}</span>
                @endif
            </a>
            <a href="{{ route('shop.profile') }}" class="{{ $nav === 'profile' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">◍</span> Profile
            </a>

            <div class="group-label">Account</div>
            <a href="{{ route('shop.status') }}" class="{{ $nav === 'status' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">⚑</span> Shop status
            </a>
            <a href="{{ route('client.home') }}">
                <span class="ico" aria-hidden="true">↗</span> View storefront
            </a>
            <form method="POST" action="{{ route('shop.logout') }}">
                @csrf
                <button type="submit" class="panel-logout" style="all:unset;display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:10px;width:100%;cursor:pointer;font-size:.91rem;color:rgba(255,255,255,.78)">
                    <span class="ico" aria-hidden="true">⎋</span> Log out
                </button>
            </form>
        </nav>
    </aside>

    <div class="panel-main">
        <header class="panel-top">
            <h1>@yield('page-title', 'Dashboard')</h1>
            <div class="spacer"></div>
            <div class="top-user">
                <span>{{ auth()->user()?->phone }}</span>
                <span class="badge badge-{{ $navShop?->status ?? 'inactive' }}">{{ ucfirst($navShop?->status ?? 'unknown') }}</span>
            </div>
        </header>

        <div class="panel-body @yield('body-class')">
            @if (session('status'))
                <div class="alert alert-success mb-2" role="status">{{ session('status') }}</div>
            @endif
            @if (session('claim_url'))
                <div class="alert alert-info mb-2">
                    <div>
                        <strong>One-time claim link</strong>
                        <div class="mono mt-1" style="word-break:break-all">{{ session('claim_url') }}</div>
                    </div>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger mb-2" role="alert">
                    <div>
                        <strong>Please check the following:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>

@include('partials.flash-data')
<script src="{{ asset('assets/marketplace.js') }}" defer></script>
@stack('scripts')
</body>
</html>
