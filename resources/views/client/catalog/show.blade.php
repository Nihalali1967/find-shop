@extends('layouts.public')

@section('title', $product->name.' · '.config('marketplace.brand'))

@php
    $cover = $product->images->first();
    $effective = $product->offer_price_paise ?? $product->price_paise;
    $unitLabel = $product->unitLabel();
    $qty = \App\Support\Money::quantity($product->unit_count);
    $isOwnShop = auth()->check() && $product->shop?->owner_id === auth()->id();
@endphp

@section('content')
    <div class="wrap detail-layout">
        <div>
            <div class="gallery-main">
                @if ($cover)
                    <img data-gallery-main src="{{ $cover->url() }}" alt="{{ $product->name }} — main image" width="1200" height="900">
                @else
                    <div class="empty"><div class="glyph" aria-hidden="true">▢</div><h3>No images yet</h3></div>
                @endif
            </div>

            @if ($product->images->count() > 1)
                <div class="gallery-thumbs">
                    @foreach ($product->images as $index => $image)
                        <button type="button" class="{{ $index === 0 ? 'is-on' : '' }}"
                                data-gallery-thumb="{{ $image->url() }}"
                                aria-label="Show image {{ $index + 1 }}">
                            <img src="{{ $image->thumbUrl() }}" alt="" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('client.home') }}">Catalog</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('client.products.index', ['category_id' => $product->subcategory?->category?->id]) }}">{{ $product->subcategory?->category?->name }}</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('client.products.index', ['subcategory_id' => $product->subcategory?->id]) }}">{{ $product->subcategory?->name }}</a>
            </nav>

            <h1>{{ $product->name }}</h1>
            <p class="muted">{{ $product->title }}</p>

            <div class="detail-price-block">
                <div class="flex center wrap-gap gap-2">
                    <span class="big">₹{{ \App\Support\Money::format($effective, false) }}</span>
                    @if ($product->offer_price_paise)
                        <span class="price-was">₹{{ \App\Support\Money::format($product->price_paise, false) }}</span>
                        <span class="badge badge-offer">Offer</span>
                    @endif
                </div>
                @php
                    $pct = $product->offer_price_paise ? round((1 - $effective / max(1, $product->price_paise)) * 100) : 0;
                @endphp
                <div class="unit-note mt-1">
                    for {{ $qty }} {{ \Illuminate\Support\Str::plural($unitLabel, (float) $qty === 1.0 ? 1 : 2) }}
                    @if ($pct > 0) · {{ $pct }}% below the listed price @endif
                </div>
            </div>

            <ul class="spec-list">
                <li><span class="k">Category</span><span>{{ $product->subcategory?->category?->name }}</span></li>
                <li><span class="k">Subcategory</span><span>{{ $product->subcategory?->name }}</span></li>
                <li><span class="k">Sold as</span><span>{{ $qty }} {{ \Illuminate\Support\Str::plural($unitLabel, (float) $qty === 1.0 ? 1 : 2) }}</span></li>
                <li>
                    <span class="k">Colours</span>
                    <span class="chip-row" style="gap:6px">
                        @forelse ($product->colors as $color)
                            <span class="chip" style="cursor:default">
                                <span class="swatch {{ $color->is_multicolor ? 'swatch-multicolor' : '' }}"
                                      style="{{ $color->is_multicolor ? '' : 'background:'.($color->hex ?: '#ddd') }}"
                                      aria-hidden="true"></span>
                                {{ $color->name }}
                            </span>
                        @empty
                            <span class="muted">Not specified</span>
                        @endforelse
                    </span>
                </li>
                <li><span class="k">Listed</span><span>{{ $product->published_at?->format('d M Y') ?? $product->created_at->format('d M Y') }}</span></li>
            </ul>

            <div class="shop-card">
                <span class="avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($product->shop->name, 0, 1)) }}</span>
                <div style="min-width:0">
                    <strong>{{ $product->shop->name }}</strong>
                    <div class="muted" style="font-size:.85rem">
                        {{ $product->shop->locality }}, {{ $product->shop->pincode }}
                    </div>
                </div>
                @if ($product->shop->status !== 'active')
                    <span class="badge badge-{{ $product->shop->status }}">{{ ucfirst($product->shop->status) }}</span>
                @endif
            </div>

            <div class="btn-row mt-3">
                @if ($isOwnShop)
                    <span class="alert alert-info" style="flex:1">This is your own listing — open it in the shop panel to manage enquiries.</span>
                    <a href="{{ route('shop.products.edit', $product) }}" class="btn btn-primary">Edit in shop panel</a>
                @else
                    <a href="{{ route('client.chat.start', $product) }}" class="btn btn-primary btn-lg">
                        Chat with shop
                    </a>
                    @guest
                        <span class="hint">You will verify your mobile number first, then return to this product.</span>
                    @endguest
                @endif
            </div>

            <section class="mt-3">
                <h2>Description</h2>
                <p style="white-space:pre-wrap">{{ $product->description }}</p>
            </section>
        </div>
    </div>

    @if ($suggestions->isNotEmpty())
        <div class="wrap" style="padding-bottom:56px">
            <h2>More in {{ $product->subcategory->name }}</h2>
            <div class="product-grid mt-2">
                @foreach ($suggestions as $suggestion)
                    <x-product-card :product="$suggestion" />
                @endforeach
            </div>
        </div>
    @endif
@endsection
