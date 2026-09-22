@extends('layouts.auth')

@section('title', 'Enter your code')

@section('content')
    <h1 style="font-size:1.35rem">Enter the 6-digit code</h1>
    <p class="muted">We sent a code to <strong>{{ $phone }}</strong>. It expires in five minutes.</p>

    @if (config('services.sms.driver') === 'fake')
        @php $devOtp = \App\Services\Sms\FakeSmsGateway::last(); @endphp
        <div class="dev-note mb-2">
            <strong>Local development mode.</strong>
            @if ($devOtp)
                Your code is <span class="mono">{{ $devOtp['code'] }}</span>.
            @else
                Send a code above to reveal it here.
            @endif
            Production uses a real SMS provider and never exposes the code.
        </div>
    @endif

    <form method="POST" action="{{ route('client.otp.verify.submit') }}">
        @csrf
        <div class="field">
            <label for="code">One-time code</label>
            <input id="code" class="otp-input @error('code') is-invalid @enderror" type="text"
                   name="code" inputmode="numeric" autocomplete="one-time-code"
                   maxlength="6" pattern="[0-9]{6}" required autofocus>
            <x-field-error name="code" />
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Verify and continue</button>
    </form>

    <form method="POST" action="{{ route('client.otp.resend') }}" class="mt-2">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">
        <input type="hidden" name="purpose" value="client_login">
        <button type="submit" class="btn btn-ghost btn-block">Resend code</button>
    </form>

    <p class="hint mt-2">Codes are rate limited. If you requested several, the newest one is the only valid code.</p>
@endsection
