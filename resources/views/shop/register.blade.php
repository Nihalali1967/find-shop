@extends('layouts.public')

@section('title', 'Create your shop')

@section('content')
    <div class="wrap" style="padding:40px 0 64px">
        <div class="grid grid-2" style="grid-template-columns:minmax(0,1fr) minmax(0,1.25fr);align-items:start">
            <div>
                <span class="eyebrow-soft">Step 2 of 2</span>
                <h1>Create your shop profile</h1>
                <p class="muted">
                    Your mobile <strong>{{ $user->phone }}</strong> is verified. Tell buyers who you are; you can edit contact details later from the shop panel.
                </p>
                <ul class="spec-list">
                    <li><span class="k">Shop name</span><span>Must be unique across the marketplace</span></li>
                    <li><span class="k">Location</span><span>Locality, six-digit pincode and full address</span></li>
                    <li><span class="k">Optional</span><span>GSTIN, secondary phone and email</span></li>
                </ul>
                <div class="alert alert-info">
                    <div>
                        Your shop becomes active immediately. Buyers only see your published products and the public shop details below.
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <form method="POST" action="{{ route('shop.register.store') }}">
                    @csrf

                    <fieldset>
                        <legend>Shop identity</legend>

                        <div class="field">
                            <label for="name">Shop name</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" required
                                   data-name-check data-check-url="{{ route('shop.name.check') }}"
                                   class="@error('name') is-invalid @enderror" autocomplete="organization">
                            <div class="hint" data-name-check-result></div>
                            <x-field-error name="name" />
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Location</legend>

                        <div class="form-grid">
                            <div class="field">
                                <label for="locality">Locality / city</label>
                                <input id="locality" type="text" name="locality" value="{{ old('locality') }}" required
                                       class="@error('locality') is-invalid @enderror">
                                <x-field-error name="locality" />
                            </div>

                            <div class="field">
                                <label for="pincode">Pincode</label>
                                <input id="pincode" type="text" name="pincode" value="{{ old('pincode') }}" required
                                       inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                                       class="@error('pincode') is-invalid @enderror">
                                <x-field-error name="pincode" />
                            </div>

                            <div class="field col-span-2">
                                <label for="address">Full address</label>
                                <textarea id="address" name="address" required maxlength="400"
                                          class="@error('address') is-invalid @enderror">{{ old('address') }}</textarea>
                                <x-field-error name="address" />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Optional details</legend>

                        <div class="form-grid">
                            <div class="field">
                                <label for="gst_number">GSTIN <span class="opt">(optional)</span></label>
                                <input id="gst_number" type="text" name="gst_number" value="{{ old('gst_number') }}"
                                       maxlength="15" class="@error('gst_number') is-invalid @enderror">
                                <x-field-error name="gst_number" />
                            </div>

                            <div class="field">
                                <label for="secondary_phone">Secondary phone <span class="opt">(optional)</span></label>
                                <input id="secondary_phone" type="tel" name="secondary_phone" value="{{ old('secondary_phone') }}"
                                       class="@error('secondary_phone') is-invalid @enderror">
                                <x-field-error name="secondary_phone" />
                            </div>

                            <div class="field col-span-2">
                                <label for="email">Email <span class="opt">(optional)</span></label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                       class="@error('email') is-invalid @enderror">
                                <x-field-error name="email" />
                            </div>
                        </div>
                    </fieldset>

                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary btn-lg">Create shop and continue</button>
                        <span class="hint">Your shop name is checked again when you submit.</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
