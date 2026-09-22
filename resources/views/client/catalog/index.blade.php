@extends('layouts.public')

@section('title', 'Browse building materials · '.config('marketplace.brand'))

@php
    $selectedColors = array_map('intval', (array) ($filters['colors'] ?? []));
    $subcategoryMap = [];
    foreach ($categories as $category) {
        $subcategoryMap[$category->id] = $category->subcategories->pluck('id')->all();
    }
    $sortLabels = [
        'relevance' => 'Relevance',
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name_asc' => 'Name: A–Z',
    ];
    $correction = $parsed['correction'] ?? null;
@endphp

@section('content')
    <section class="hero">
        <div class="wrap hero-inner">
            <span class="eyebrow">Materials marketplace</span>
            <h1>Find the material, then talk to the shop directly.</h1>
            <p>Search across flooring, stone, sanitary ware, lighting and finishes. Prices show the quantity they cover — enquire with the shop, no cart required.</p>

            <form method="GET" action="{{ route('client.products.index') }}" role="search" data-filter-form>
                <div class="search-bar">
                    <label class="sr-only" for="q">Search materials</label>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                           placeholder="Try “floor marble white”…" autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-lg">Search</button>
                </div>
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? '' }}">
            </form>
        </div>
    </section>

    <div class="wrap catalog-layout">
        <aside class="filters" aria-label="Filters">
            <form method="GET" action="{{ route('client.products.index') }}" data-filter-form>
                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">

                <h3>Category</h3>
                <label class="sr-only" for="category_id">Category</label>
                <select id="category_id" name="category_id" data-autosubmit data-category-select>
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                <h3>Subcategory</h3>
                <label class="sr-only" for="subcategory_id">Subcategory</label>
                <select id="subcategory_id" name="subcategory_id" data-autosubmit data-subcategory-select
                        data-map="{{ json_encode($subcategoryMap) }}">
                    <option value="">All subcategories</option>
                    @foreach ($categories as $category)
                        @foreach ($category->subcategories as $subcategory)
                            <option value="{{ $subcategory->id }}" @selected((int) ($filters['subcategory_id'] ?? 0) === $subcategory->id)>
                                {{ $subcategory->name }}
                            </option>
                        @endforeach
                    @endforeach
                </select>

                <h3>Colours</h3>
                <div class="chip-row">
                    @foreach ($colors as $color)
                        <label class="chip {{ in_array($color->id, $selectedColors, true) ? 'is-on' : '' }}">
                            <input type="checkbox" name="colors[]" value="{{ $color->id }}" data-autosubmit
                                   @checked(in_array($color->id, $selectedColors, true))>
                            <span class="swatch {{ $color->is_multicolor ? 'swatch-multicolor' : '' }}"
                                  style="{{ $color->is_multicolor ? '' : 'background:'.($color->hex ?: '#ddd') }}"
                                  aria-hidden="true"></span>
                            <span>{{ $color->name }}</span>
                        </label>
                    @endforeach
                </div>

                <h3>Price range (₹)</h3>
                <div class="form-grid" style="gap:0 10px">
                    <div class="field">
                        <label class="sr-only" for="min_price">Minimum price</label>
                        <input id="min_price" type="number" name="min_price" min="0" step="1"
                               value="{{ request('min_price') }}" placeholder="Min">
                    </div>
                    <div class="field">
                        <label class="sr-only" for="max_price">Maximum price</label>
                        <input id="max_price" type="number" name="max_price" min="0" step="1"
                               value="{{ request('max_price') }}" placeholder="Max">
                    </div>
                </div>
                <p class="hint">Price filters compare the offer price when one is set.</p>

                <h3>Sort</h3>
                <label class="sr-only" for="sort">Sort results</label>
                <select id="sort" name="sort">
                    @foreach ($sortLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['sort'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <div class="btn-row mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
                    <a href="{{ route('client.products.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                </div>
            </form>
        </aside>

        <section aria-label="Products">
            <div class="catalog-toolbar">
                <span class="count">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('listing', $products->total()) }} found</span>
                <span class="count">
                    Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }}
                </span>
            </div>

            @if ($correction)
                <div class="alert alert-info result-note">
                    <div>
                        Showing results for <strong>{{ $correction['to'] }}</strong> instead of “{{ $correction['from'] }}”.
                        <a href="{{ route('client.products.index', array_filter(['q' => $parsed['original_query']])) }}">Search the original word instead</a>.
                    </div>
                </div>
            @endif

            @if ($products->isEmpty())
                <x-empty-state title="No materials matched" glyph="⌕"
                    message="Try a different word, widen the price range, or clear a filter.">
                    <a href="{{ route('client.products.index') }}" class="btn btn-primary btn-sm mt-2">Clear filters</a>
                </x-empty-state>
            @else
                <div class="product-grid">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                {{ $products->links('components.pagination') }}
            @endif
        </section>
    </div>
@endsection
