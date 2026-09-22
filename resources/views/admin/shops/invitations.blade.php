@extends('layouts.admin')

@section('title', 'Shop invitations')
@section('nav', 'invitations')
@section('page-title', 'Shop invitations')

@section('content')
    <div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.35fr);align-items:start">
        <div class="card card-pad">
            <h2>Prepare a shop</h2>
            <p class="hint">
                An invitation is not a live shop. The intended owner claims it by verifying the same mobile number, then the
                shop is created with this profile.
            </p>

            <form method="POST" action="{{ route('admin.invitations.store') }}">
                @csrf

                <div class="field">
                    <label for="name">Shop name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="120">
                    <div class="hint">Checked against existing shops and pending invitations.</div>
                    <x-field-error name="name" />
                </div>

                <div class="field">
                    <label for="intended_phone">Owner mobile number</label>
                    <input id="intended_phone" type="tel" name="intended_phone" value="{{ old('intended_phone') }}" required>
                    <div class="hint">The claim must be verified with this exact number.</div>
                    <x-field-error name="intended_phone" />
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="locality">Locality / city</label>
                        <input id="locality" type="text" name="locality" value="{{ old('locality') }}" required>
                        <x-field-error name="locality" />
                    </div>
                    <div class="field">
                        <label for="pincode">Pincode</label>
                        <input id="pincode" type="text" name="pincode" value="{{ old('pincode') }}" required maxlength="6" pattern="[0-9]{6}">
                        <x-field-error name="pincode" />
                    </div>
                    <div class="field col-span-2">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" required maxlength="400">{{ old('address') }}</textarea>
                        <x-field-error name="address" />
                    </div>
                    <div class="field">
                        <label for="intended_status">Intended status</label>
                        <select id="intended_status" name="intended_status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="expires_in_days">Expires in (days)</label>
                        <input id="expires_in_days" type="number" name="expires_in_days" value="{{ old('expires_in_days', 14) }}" min="1" max="60" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create invitation</button>
            </form>
        </div>

        <x-dt-toolbar :paginator="$invitations" placeholder="Shop name or owner phone…" search-label="Search invitations">
            <div class="dt-field">
                <label class="sr-only" for="inv-state">State</label>
                <select id="inv-state" name="state" data-autosubmit>
                    <option value="">All states</option>
                    <option value="pending" @selected(request('state') === 'pending')>Pending</option>
                    <option value="claimed" @selected(request('state') === 'claimed')>Claimed</option>
                    <option value="expired" @selected(request('state') === 'expired')>Expired</option>
                </select>
            </div>
        </x-dt-toolbar>

        <div class="card" id="invitations-region" data-dt-region>
            <div class="card-head">
                <h2>Invitations</h2>
                <span class="muted" style="font-size:.84rem">Claim links are shown once on creation</span>
            </div>

            <div class="card-body" style="padding:0 20px 4px">
                <x-dt-info :paginator="$invitations" label="invitation" :q="request('q')" />
            </div>

            @if ($invitations->isEmpty())
                <x-empty-state title="No invitations matched" glyph="✚"
                    message="Try a different search, clear the filters, or create one on the left." />
            @else
                <div class="table-wrap" style="border:0;border-radius:0">
                    <table class="table">
                        <thead>
                            <tr>
                                <x-dt-th col="name">Shop</x-dt-th>
                                <th>Intended owner</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invitations as $invitation)
                                <tr>
                                    <td>
                                        <strong>{{ $invitation->name }}</strong>
                                        <div class="muted" style="font-size:.78rem">{{ $invitation->profile['locality'] ?? '' }}</div>
                                    </td>
                                    <td class="nowrap">{{ $invitation->intended_phone }}</td>
                                    <td>
                                        @if ($invitation->claimed_at)
                                            <span class="badge badge-published">Claimed</span>
                                        @elseif ($invitation->expires_at->isPast())
                                            <span class="badge badge-suspended">Expired</span>
                                        @else
                                            <span class="badge badge-inactive">Pending</span>
                                        @endif
                                    </td>
                                    <td class="nowrap">{{ $invitation->expires_at->format('d M Y') }}</td>
                                    <td>
                                        <div class="table-actions" style="justify-content:flex-end">
                                            @if ($invitation->shop)
                                                <a href="{{ route('admin.shops.show', $invitation->shop) }}" class="btn btn-sm btn-ghost">Open shop</a>
                                            @endif
                                            @unless ($invitation->claimed_at)
                                                <form method="POST" action="{{ route('admin.invitations.destroy', $invitation) }}"
                                                      data-confirm="Revoke this invitation?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-foot">{{ $invitations->links('components.pagination') }}</div>
            @endif
        </div>
    </div>
@endsection
