@extends('layouts.auth')

@section('title', 'Shop sign in')

@section('content')
    <h1 style="font-size:1.35rem">Shop owner sign in</h1>
    <p class="muted">Use the mobile number registered with your shop. New owners can register right after verifying.</p>

    <form method="POST" action="{{ route('shop.otp.request') }}">
        @csrf
        <div class="field">
            <label for="phone">Registered mobile number</label>
            <div class="input-group">
                <span class="prefix">+91</span>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                       inputmode="numeric" autocomplete="tel" required autofocus
                       placeholder="98765 43210" class="@error('phone') is-invalid @enderror">
            </div>
            <x-field-error name="phone" />
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Send one-time code</button>
    </form>

    <div class="alert alert-info mt-3">
        <div>
            <strong>Shop account restricted?</strong>
            Verify your number and the status screen explains what to do next.
        </div>
    </div>
@endsection
