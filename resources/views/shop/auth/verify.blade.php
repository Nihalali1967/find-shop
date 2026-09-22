@extends('layouts.auth')

@section('title', 'Shop verification')

@section('content')
    <h1 style="font-size:1.35rem">Confirm your shop number</h1>
    <p class="muted">We sent a code to <strong>{{ $phone }}</strong>.</p>

    @if (config('services.sms.driver') === 'fake')
        @php $devOtp = \App\Services\Sms\FakeSmsGateway::last(); @endphp
        <div class="dev-note mb-2">
            <strong>Local development mode.</strong>
            @if ($devOtp)
                Your code is <span class="mono">{{ $devOtp['code'] }}</span>.
            @else
                Send a code first to reveal it here.
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('shop.otp.verify.submit') }}">
        @csrf
        <div class="field">
            <label for="code">One-time code</label>
            <input id="code" class="otp-input @error('code') is-invalid @enderror" type="text"
                   name="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="6" pattern="[0-9]{6}" required autofocus>
            <x-field-error name="code" />
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Verify</button>
    </form>

    <p class="hint mt-3"><a href="{{ route('shop.login') }}">Use a different number</a></p>
@endsection
