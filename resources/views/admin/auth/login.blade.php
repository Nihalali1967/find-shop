@extends('layouts.auth')

@section('title', 'Admin sign in')

@section('content')
    <h1 style="font-size:1.35rem">Administrator sign in</h1>
    <p class="muted">Admin access uses email and password on a separate guard. Customer mobile codes cannot open this console.</p>

    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="@error('email') is-invalid @enderror">
            <x-field-error name="email" />
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="@error('password') is-invalid @enderror">
            <x-field-error name="password" />
        </div>

        <label class="checkline mb-2">
            <input type="checkbox" name="remember" value="1">
            <span>Keep me signed in on this device</span>
        </label>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Sign in</button>
    </form>

    <p class="hint mt-3">Login attempts are rate limited. Provision accounts with <span class="mono">php artisan admin:create</span>.</p>
@endsection
