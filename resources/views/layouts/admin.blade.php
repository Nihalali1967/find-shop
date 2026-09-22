@php
    $nav = trim($__env->yieldContent('nav')) ?: 'dashboard';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · {{ config('marketplace.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/marketplace.css') }}">
    @stack('head')
</head>
<body class="admin-theme">
<a class="skip-link" href="#main">Skip to content</a>

<div class="panel">
    <aside class="panel-side">
        <a class="brand" href="{{ route('admin.dashboard') }}">
            <span class="mark" aria-hidden="true">A</span>
            <span>Admin console</span>
        </a>

        <nav class="panel-nav" aria-label="Admin">
            <a href="{{ route('admin.dashboard') }}" class="{{ $nav === 'dashboard' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">▤</span> Dashboard
            </a>
            <a href="{{ route('admin.shops.index') }}" class="{{ $nav === 'shops' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">◧</span> Shops
            </a>
            <a href="{{ route('admin.invitations.index') }}" class="{{ $nav === 'invitations' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">✚</span> Invitations
            </a>
            <a href="{{ route('admin.taxonomy.index') }}" class="{{ $nav === 'taxonomy' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">⌗</span> Categories
            </a>
            <a href="{{ route('admin.colors.index') }}" class="{{ $nav === 'colors' ? 'is-active' : '' }}">
                <span class="ico" aria-hidden="true">◍</span> Colors
            </a>

            <div class="group-label">Session</div>
            <a href="{{ route('client.home') }}">
                <span class="ico" aria-hidden="true">↗</span> View storefront
            </a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" style="all:unset;display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:10px;width:100%;cursor:pointer;font-size:.91rem;color:rgba(255,255,255,.78)">
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
                <span>{{ auth('admin')->user()?->email }}</span>
                <span class="badge badge-info">Administrator</span>
            </div>
        </header>

        <div class="panel-body @yield('body-class')">
            @if (session('status'))
                <div class="alert alert-success mb-2" role="status">{{ session('status') }}</div>
            @endif
            @if (session('claim_url'))
                <div class="alert alert-info mb-2">
                    <div>
                        <strong>One-time claim link — share it with the owner</strong>
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
