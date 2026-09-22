@extends('layouts.shop')

@section('title', 'Products')
@section('nav', 'products')
@section('page-title', 'Products')

@section('content')
    <x-dt-toolbar :paginator="$products" placeholder="Name or title…" search-label="Search your products">
        <div class="dt-field">
            <label class="sr-only" for="status">Status</label>
            <select id="status" name="status" data-autosubmit>
                <option value="">All statuses</option>
                <option value="published" @selected(request('status') === 'published')>Published</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
        </div>
        <div class="dt-field">
            <label class="sr-only" for="category_id">Category</label>
            <select id="category_id" name="category_id" data-autosubmit>
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) request('category_id') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('shop.products.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        <a href="{{ route('shop.products.create') }}" class="btn btn-accent btn-sm">+ Add product</a>
    </x-dt-toolbar>

    <div class="card" id="products-region" data-dt-region>
        <div class="card-head">
            <h2>Products</h2>
        </div>

        <div class="card-body" style="padding:0 20px 4px">
            <x-dt-info :paginator="$products" label="product" :q="request('q')" />
        </div>

        @if ($products->isEmpty())
            <x-empty-state title="No products matched" glyph="▦"
                message="Adjust the filters, or create your first listing.">
                <a href="{{ route('shop.products.create') }}" class="btn btn-primary btn-sm mt-2">Create a product</a>
            </x-empty-state>
        @else
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="table">
                    <thead>
                        <tr>
                            <x-dt-th col="name">Product</x-dt-th>
                            <th>Category</th>
                            <x-dt-th col="price">Price</x-dt-th>
                            <th>Images</th>
                            <x-dt-th col="status">Status</x-dt-th>
                            <th class="text-right">Actions</th>
                            <x-dt-th col="created_at">Added</x-dt-th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php $cover = $product->images->first(); @endphp
                            <tr>
                                <td>
                                    <div class="flex center gap-2">
                                        @if ($cover)
                                            <img src="{{ $cover->thumbUrl() }}"
                                                 alt="" width="56" height="44" style="border-radius:8px;object-fit:cover">
                                        @else
                                            <span class="no-img" style="width:56px;height:44px;border-radius:8px;background:var(--ivory-2);display:grid;place-items:center;font-size:.66rem;color:var(--muted)">none</span>
                                        @endif
                                        <div style="min-width:0">
                                            <a href="{{ route('shop.products.edit', $product) }}"><strong>{{ $product->name }}</strong></a>
                                            <div class="muted" style="font-size:.78rem">{{ \Illuminate\Support\Str::limit($product->title, 52) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:.85rem">{{ $product->subcategory?->category?->name }}</div>
                                    <div class="muted" style="font-size:.78rem">{{ $product->subcategory?->name }}</div>
                                </td>
                                <td class="nowrap">
                                    ₹{{ \App\Support\Money::format($product->effectivePricePaise(), false) }}
                                    @if ($product->offer_price_paise)
                                        <div class="price-was" style="font-size:.76rem">₹{{ \App\Support\Money::format($product->price_paise, false) }}</div>
                                    @endif
                                    <div class="muted" style="font-size:.75rem">per {{ \App\Support\Money::quantity($product->unit_count) }} {{ $product->unitLabel() }}</div>
                                </td>
                                <td>{{ $product->images->count() }}</td>
                                <td><x-status-badge :status="$product->status" /></td>
                                <td>
                                    <div class="table-actions" style="justify-content:flex-end">
                                        <a href="{{ route('shop.products.edit', $product) }}" class="btn btn-sm btn-ghost">Edit</a>
                                        @if ($product->isPublished())
                                            <a href="{{ route('client.products.show', $product) }}" class="btn btn-sm btn-ghost" target="_blank" rel="noopener">View</a>
                                        @endif
                                        <form method="POST" action="{{ route('shop.products.destroy', $product) }}"
                                              data-confirm="Delete “{{ $product->name }}”? Chat history is preserved but the listing disappears from the catalog.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                                <td class="nowrap">{{ $product->created_at?->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-foot">{{ $products->links('components.pagination') }}</div>
        @endif
    </div>
@endsection
