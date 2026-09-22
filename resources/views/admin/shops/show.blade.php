@extends('layouts.admin')

@section('title', $shop->name)
@section('nav', 'shops')
@section('page-title', $shop->name)

@php $deleted = $shop->trashed(); @endphp

@section('content')
    <div class="flex between center wrap-gap mb-2">
        <a href="{{ route('admin.shops.index') }}" class="btn btn-ghost btn-sm">← All shops</a>
        <div class="flex center gap-1">
            <x-status-badge :status="$deleted ? 'deleted' : $shop->status" />
            <span class="muted" style="font-size:.85rem">Owner {{ $shop->owner?->phone }}</span>
        </div>
    </div>

    @if ($deleted)
        <div class="alert alert-danger mb-2">
            <div>This shop is soft-deleted. Public listings are hidden and it keeps its chat history without accepting new enquiries.</div>
        </div>
    @endif

    <div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);align-items:start">
        <div class="stack">
            <div class="card card-pad">
                <h2>Shop profile</h2>
                <p class="hint">Ownership cannot be changed here. Contact detail edits never transfer the shop.</p>

                <form method="POST" action="{{ route('admin.shops.update', $shop) }}">
                    @csrf
                    @method('PATCH')
                    <div class="form-grid">
                        <div class="field col-span-2">
                            <label for="name">Shop name</label>
                            <input id="name" type="text" name="name" value="{{ old('name', $shop->name) }}" required>
                            <x-field-error name="name" />
                        </div>
                        <div class="field">
                            <label for="locality">Locality</label>
                            <input id="locality" type="text" name="locality" value="{{ old('locality', $shop->locality) }}" required>
                            <x-field-error name="locality" />
                        </div>
                        <div class="field">
                            <label for="pincode">Pincode</label>
                            <input id="pincode" type="text" name="pincode" value="{{ old('pincode', $shop->pincode) }}" required maxlength="6" pattern="[0-9]{6}">
                            <x-field-error name="pincode" />
                        </div>
                        <div class="field col-span-2">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" required maxlength="400">{{ old('address', $shop->address) }}</textarea>
                            <x-field-error name="address" />
                        </div>
                        <div class="field">
                            <label for="gst_number">GSTIN</label>
                            <input id="gst_number" type="text" name="gst_number" value="{{ old('gst_number', $shop->gst_number) }}" maxlength="15">
                        </div>
                        <div class="field">
                            <label for="secondary_phone">Secondary phone</label>
                            <input id="secondary_phone" type="tel" name="secondary_phone" value="{{ old('secondary_phone', $shop->secondary_phone) }}">
                        </div>
                        <div class="field col-span-2">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $shop->email) }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save profile</button>
                </form>
            </div>

            <div class="card">
                <div class="card-head"><h2>Products ({{ $products->total() }})</h2></div>
                @if ($products->isEmpty())
                    <x-empty-state title="No products" glyph="▦" message="This shop has not listed anything yet." />
                @else
                    <div class="table-wrap" style="border:0;border-radius:0">
                        <table class="table">
                            <thead><tr><th>Product</th><th>Price</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($products as $product)
                                    <tr>
                                        <td>
                                            <strong>{{ $product->name }}</strong>
                                            <div class="muted" style="font-size:.78rem">{{ $product->subcategory?->category?->name }} · {{ $product->subcategory?->name }}</div>
                                        </td>
                                        <td class="nowrap">₹{{ \App\Support\Money::format($product->effectivePricePaise(), false) }}</td>
                                        <td><x-status-badge :status="$product->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-foot">{{ $products->links('components.pagination') }}</div>
                @endif
            </div>
        </div>

        <div class="stack">
            @unless ($deleted)
                <div class="card card-pad">
                    <h2>Status</h2>
                    <p class="hint">Inactive and suspended shops disappear from the catalog immediately and cannot send messages through old sessions or sockets.</p>

                    <form method="POST" action="{{ route('admin.shops.status', $shop) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field">
                            <label for="status">New status</label>
                            <select id="status" name="status" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($shop->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="reason">Reason</label>
                            <input id="reason" type="text" name="reason" maxlength="500" required
                                   value="{{ old('reason') }}" placeholder="Shown to the owner and stored in history">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Apply status change</button>
                    </form>
                </div>
            @endunless

            <div class="card">
                <div class="card-head"><h2>Status history</h2></div>
                <div class="card-body">
                    @if ($shop->statusEvents->isEmpty())
                        <p class="muted mb-0">No changes recorded.</p>
                    @else
                        <ul class="timeline">
                            @foreach ($shop->statusEvents as $event)
                                <li>
                                    <div class="flex between wrap-gap">
                                        <strong>{{ ucfirst($event->to_status) }}</strong>
                                        <span class="muted" style="font-size:.78rem">{{ $event->created_at->format('d M Y, H:i') }}</span>
                                    </div>
                                    @if ($event->reason)
                                        <div class="muted" style="font-size:.85rem">{{ $event->reason }}</div>
                                    @endif
                                    <div class="muted" style="font-size:.74rem">{{ $event->actor_type ?? 'system' }} #{{ $event->actor_id ?? '—' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card card-pad">
                <h2>Danger zone</h2>
                <p class="hint mb-2">Soft deletion hides the shop and its listings while retaining chat history.</p>
                <form method="POST" action="{{ route('admin.shops.destroy', $shop) }}"
                      data-confirm="Soft-delete “{{ $shop->name }}”? Listings disappear from the catalog and no new enquiries are accepted.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-block" @disabled($deleted)>Delete shop</button>
                </form>
            </div>
        </div>
    </div>
@endsection
