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

                            <div class="field col-span-2">
                                <label>Shop location on map</label>
                                <div class="hint" style="margin-bottom:8px">
                                    Click the map or drag the pin to your shop. You can also type the coordinates below.
                                </div>
                                <div class="btn-row" style="margin-bottom:8px">
                                    <button type="button" class="btn btn-sm" data-locate-me>Use my current location</button>
                                    <span class="hint" data-locate-status></span>
                                </div>
                                <div id="shop-map" style="height:300px;border-radius:12px;border:1px solid #d9d9d9;z-index:0"></div>
                            </div>

                            <div class="field">
                                <label for="latitude">Latitude</label>
                                <input id="latitude" type="number" name="latitude" value="{{ old('latitude') }}" required
                                       step="any" min="-90" max="90" inputmode="decimal" placeholder="e.g. 12.9716"
                                       class="@error('latitude') is-invalid @enderror">
                                <x-field-error name="latitude" />
                            </div>

                            <div class="field">
                                <label for="longitude">Longitude</label>
                                <input id="longitude" type="number" name="longitude" value="{{ old('longitude') }}" required
                                       step="any" min="-180" max="180" inputmode="decimal" placeholder="e.g. 77.5946"
                                       class="@error('longitude') is-invalid @enderror">
                                <x-field-error name="longitude" />
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

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (function () {
            var latInput = document.getElementById('latitude');
            var lngInput = document.getElementById('longitude');
            var status = document.querySelector('[data-locate-status]');

            var hasValue = latInput.value !== '' && lngInput.value !== '';
            var start = hasValue ? [parseFloat(latInput.value), parseFloat(lngInput.value)] : [12.9716, 77.5946];

            var map = L.map('shop-map').setView(start, hasValue ? 16 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            var marker = null;

            function setPoint(lat, lng, pan) {
                lat = Math.round(lat * 1e7) / 1e7;
                lng = Math.round(lng * 1e7) / 1e7;
                latInput.value = lat;
                lngInput.value = lng;
                if (!marker) {
                    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    marker.on('dragend', function () {
                        var p = marker.getLatLng();
                        setPoint(p.lat, p.lng, false);
                    });
                } else {
                    marker.setLatLng([lat, lng]);
                }
                if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 16));
            }

            if (hasValue) setPoint(start[0], start[1], false);

            map.on('click', function (e) { setPoint(e.latlng.lat, e.latlng.lng, false); });

            // Manual typing moves the pin.
            function fromInputs() {
                var lat = parseFloat(latInput.value), lng = parseFloat(lngInput.value);
                if (isFinite(lat) && isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
                    setPoint(lat, lng, true);
                }
            }
            latInput.addEventListener('change', fromInputs);
            lngInput.addEventListener('change', fromInputs);

            document.querySelector('[data-locate-me]').addEventListener('click', function () {
                if (!navigator.geolocation) { status.textContent = 'Location is not supported in this browser.'; return; }
                status.textContent = 'Finding your location…';
                navigator.geolocation.getCurrentPosition(function (pos) {
                    setPoint(pos.coords.latitude, pos.coords.longitude, true);
                    status.textContent = 'Location set. Drag the pin to adjust.';
                }, function () {
                    status.textContent = 'Could not get your location. Click the map instead.';
                }, { enableHighAccuracy: true, timeout: 10000 });
            });
        })();
    </script>
@endpush
