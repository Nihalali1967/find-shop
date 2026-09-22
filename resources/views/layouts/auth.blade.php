<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · {{ config('marketplace.brand') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/marketplace.css') }}">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a class="brand" href="{{ route('client.home') }}">
            <span class="mark" aria-hidden="true">M</span>
            <span>{{ config('marketplace.brand') }}</span>
        </a>

        @if (session('status'))
            <div class="alert alert-success mb-2" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger mb-2" role="alert">
                <div>
                    <ul style="margin:0;padding-left:18px">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')

        <p class="hint mt-3">
            <a href="{{ route('client.home') }}">← Back to catalog</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('shop.login') }}">Shop panel</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('admin.login') }}">Admin</a>
        </p>
    </div>
</div>
<script src="{{ asset('assets/marketplace.js') }}" defer></script>
</body>
</html>
