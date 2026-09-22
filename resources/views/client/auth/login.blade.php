@extends('layouts.auth')

@section('title', 'Sign in with your mobile')

@section('content')
    <h1 style="font-size:1.35rem">Sign in or create an account</h1>
    <p class="muted">Enter your mobile number. We send a one-time code by SMS — no password to remember.</p>

    <form method="POST" action="{{ route('client.otp.request') }}">
        @csrf
        <input type="hidden" name="purpose" value="client_login">

        <div class="field">
            <label for="phone">Mobile number</label>
            <div class="input-group">
                <span class="prefix">+91</span>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                       inputmode="numeric" autocomplete="tel" required autofocus
                       placeholder="98765 43210" class="@error('phone') is-invalid @enderror">
            </div>
            <x-field-error name="phone" />
            <div class="hint">Numbers are stored in international format. Standard SMS rates apply.</div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Send one-time code</button>
    </form>

    <p class="hint mt-3">By continuing you agree that shops may reply to your enquiry inside this app.</p>
@endsection
