@extends('layouts.admin')

@section('title', 'Shops')
@section('nav', 'shops')
@section('page-title', 'Shops')

@section('content')
    <div class="card mb-2">
        <form method="GET" action="{{ route('admin.shops.index') }}" data-filter-form>
            <div class="card-body">
                <div class="grid grid-3" style="align-items:end">
                    <div class="field" style="margin:0">
                        <label for="q">Search</label>
                        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Shop name, owner phone, pincode">
                    </div>
                    <div class="field" style="margin:0">
                        <label for="status">Status</label>
                        <select id="status" name="status" data-autosubmit>
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin:0">
                        <label for="locality">Locality</label>
                        <input id="locality" type="text" name="locality" value="{{ request('locality') }}">
                    </div>
                </div>
                <div class="btn-row mt-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('admin.shops.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                    <a href="{{ route('admin.invitations.index') }}" class="btn btn-accent btn-sm">+ Create invitation</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>{{ $shops->total() }} {{ \Illuminate\Support\Str::plural('shop', $shops->total()) }}</h2>
            <span class="muted" style="font-size:.85rem">Soft-deleted shops are hidden from this list</span>
        </div>

        @if ($shops->isEmpty())
            <x-empty-state title="No shops matched" glyph="◧" message="Try a different search or clear the filters." />
        @else
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shop</th><th>Owner phone</th><th>Location</th><th>Products</th><th>Status</th><th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shops as $shop)
                            <tr>
                                <td><a href="{{ route('admin.shops.show', $shop) }}"><strong>{{ $shop->name }}</strong></a></td>
                                <td class="nowrap">{{ $shop->owner?->phone }}</td>
                                <td>{{ $shop->locality }}<div class="muted" style="font-size:.78rem">{{ $shop->pincode }}</div></td>
                                <td>{{ $shop->products_count }}</td>
                                <td><x-status-badge :status="$shop->status" /></td>
                                <td>
                                    <div class="table-actions" style="justify-content:flex-end">
                                        <a href="{{ route('admin.shops.show', $shop) }}" class="btn btn-sm btn-ghost">Open</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-foot">{{ $shops->links('components.pagination') }}</div>
        @endif
    </div>
@endsection
