@extends('layouts.shop')

@section('title', 'Shop profile')
@section('nav', 'profile')
@section('page-title', 'Shop profile')

@section('content')
    <div class="grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);align-items:start">
        <div class="card card-pad">
            <form method="POST" action="{{ route('shop.profile.update') }}">
                @csrf
                @method('PATCH')

                <fieldset>
                    <legend>Public details</legend>
                    <div class="form-grid">
                        <div class="field">
                            <label for="locality">Locality / city</label>
                            <input id="locality" type="text" name="locality" value="{{ old('locality', $shop->locality) }}" required>
                            <x-field-error name="locality" />
                        </div>
                        <div class="field">
                            <label for="pincode">Pincode</label>
                            <input id="pincode" type="text" name="pincode" value="{{ old('pincode', $shop->pincode) }}" required inputmode="numeric" maxlength="6" pattern="[0-9]{6}">
                            <x-field-error name="pincode" />
                        </div>
                        <div class="field col-span-2">
                            <label for="address">Full address</label>
                            <textarea id="address" name="address" required maxlength="400">{{ old('address', $shop->address) }}</textarea>
                            <x-field-error name="address" />
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Contact details</legend>
                    <p class="hint">These stay private. They are never shown on public product pages.</p>
                    <div class="form-grid">
                        <div class="field">
                            <label for="gst_number">GSTIN</label>
                            <input id="gst_number" type="text" name="gst_number" value="{{ old('gst_number', $shop->gst_number) }}" maxlength="15">
                            <x-field-error name="gst_number" />
                        </div>
                        <div class="field">
                            <label for="secondary_phone">Secondary phone</label>
                            <input id="secondary_phone" type="tel" name="secondary_phone" value="{{ old('secondary_phone', $shop->secondary_phone) }}">
                            <x-field-error name="secondary_phone" />
                        </div>
                        <div class="field col-span-2">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $shop->email) }}">
                            <x-field-error name="email" />
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-primary">Save profile</button>
            </form>
        </div>

        <div class="stack">
            <div class="card card-pad">
                <h3>Ownership</h3>
                <p class="muted">Owner: <strong>{{ $shop->owner?->phone }}</strong></p>
                <div class="alert alert-info">
                    <div>Editing contact details never transfers ownership. Changing the owner number requires re-verification by support.</div>
                </div>
                <form method="POST" action="{{ route('shop.logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Log out</button>
                </form>
            </div>

            <div class="card card-pad">
                <h3>Shop</h3>
                <ul class="spec-list" style="margin:0">
                    <li><span class="k">Name</span><span>{{ $shop->name }}</span></li>
                    <li><span class="k">Status</span><span><x-status-badge :status="$shop->status" /></span></li>
                    <li><span class="k">Joined</span><span>{{ $shop->created_at->format('d M Y') }}</span></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
