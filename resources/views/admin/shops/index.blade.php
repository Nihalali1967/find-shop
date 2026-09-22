@extends('layouts.admin')

@section('title', 'Shops')
@section('nav', 'shops')
@section('page-title', 'Shops')

@section('content')
    <x-dt-toolbar :paginator="$shops" placeholder="Shop name, owner phone, pincode…" search-label="Search shops">
        <div class="dt-field">
            <label class="sr-only" for="status">Status</label>
            <select id="status" name="status" data-autosubmit>
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="dt-field">
            <label class="sr-only" for="locality">Locality</label>
            <input id="locality" type="text" name="locality" value="{{ request('locality') }}" placeholder="Locality" style="max-width:150px">
        </div>
        <a href="{{ route('admin.shops.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        <a href="{{ route('admin.invitations.index') }}" class="btn btn-accent btn-sm">+ Create invitation</a>
    </x-dt-toolbar>

    <div class="card" id="shops-region" data-dt-region>
        <div class="card-head">
            <h2>Shops</h2>
            <span class="muted" style="font-size:.85rem">Soft-deleted shops are hidden from this list</span>
        </div>

        <div class="card-body" style="padding:0 20px 4px">
            <x-dt-info :paginator="$shops" label="shop" :q="request('q')" />
        </div>

        @if ($shops->isEmpty())
            <x-empty-state title="No shops matched" glyph="◧" message="Try a different search or clear the filters." />
        @else
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="table">
                    <thead>
                        <tr>
                            <x-dt-th col="name">Shop</x-dt-th>
                            <th>Owner phone</th>
                            <th>Location</th>
                            <x-dt-th col="products_count">Products</x-dt-th>
                            <x-dt-th col="status">Status</x-dt-th>
                            <th class="text-right">Actions</th>
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
