<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('marketplace.brand'))</title>
    <link rel="stylesheet" href="{{ asset('assets/marketplace.css') }}">
    @stack('head')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="public-nav">
    <div class="wrap">
        <a class="brand" href="{{ route('client.home') }}">
            <span class="mark" aria-hidden="true">M</span>
            <span>{{ config('marketplace.brand') }}</span>
        </a>

        <nav aria-label="Primary">
            <a href="{{ route('client.home') }}" class="{{ request()->routeIs('client.home', 'client.products.*') ? 'is-active' : '' }}">Browse materials</a>
            @auth
                <a href="{{ route('client.chat.index') }}" class="{{ request()->routeIs('client.chat.*') ? 'is-active' : '' }}">My chats</a>
                <a href="{{ route('client.notifications.index') }}" class="{{ request()->routeIs('client.notifications.*') ? 'is-active' : '' }}">Notifications</a>
            @endauth
            <span class="nav-portals">
                <a href="{{ route('shop.dashboard') }}">Shop panel</a>
                <a href="{{ route('admin.dashboard') }}">Admin</a>
            </span>
            @auth
                <form method="POST" action="{{ route('client.logout') }}" class="nowrap">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost" style="--btn-fg:#fff;--btn-bd:rgba(255,255,255,.35);--btn-bg:transparent">Log out</button>
                </form>
            @else
                <a href="{{ route('client.login') }}" class="btn btn-sm btn-accent">Sign in</a>
            @endauth
        </nav>
    </div>
</header>

@if (session('status'))
    <div class="wrap mt-2">
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    </div>
@endif

@if ($errors->any() && ! request()->routeIs('*.otp.*'))
    <div class="wrap mt-2">
        <div class="alert alert-danger" role="alert">
            <div>
                <strong>Please check the following:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<main id="main">
    @yield('content')
</main>

<footer class="wrap" style="padding:34px 20px 50px;color:var(--muted);font-size:.84rem">
    <div class="flex between wrap-gap center">
        <span>{{ config('marketplace.brand') }} — product discovery and direct shop enquiry. No checkout in this release.</span>
        <span>Demo data is fictional.</span>
    </div>
</footer>

@include('partials.flash-data')
<script src="{{ asset('assets/marketplace.js') }}" defer></script>
@stack('scripts')
</body>
</html>
