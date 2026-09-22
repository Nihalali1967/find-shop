@props(['product'])

@php
    $cover = $product->relationLoaded('images') ? $product->images->first() : null;
    $effective = $product->offer_price_paise ?? $product->price_paise;
    $unitLabel = $product->unitLabel();
    $qty = \App\Support\Money::quantity($product->unit_count);
@endphp

<article class="product-card">
    <a class="thumb" href="{{ route('client.products.show', $product) }}" tabindex="-1" aria-hidden="true">
        @if ($cover)
            <img src="{{ $cover->thumbUrl() }}"
                 alt="" loading="lazy" width="480" height="360">
        @else
            <span class="placeholder">No image yet</span>
        @endif
        @if ($product->offer_price_paise)
            <span class="badge badge-offer flag">Offer</span>
        @endif
    </a>

    <div class="body">
        <span class="cat">{{ $product->subcategory?->category?->name }} · {{ $product->subcategory?->name }}</span>

        <h3><a href="{{ route('client.products.show', $product) }}">{{ $product->name }}</a></h3>

        <p class="unit-note" style="margin:0">{{ \Illuminate\Support\Str::limit($product->title, 78) }}</p>

        @if ($product->relationLoaded('colors') && $product->colors->isNotEmpty())
            <div class="card-meta">
                @foreach ($product->colors->take(5) as $color)
                    <span class="swatch {{ $color->is_multicolor ? 'swatch-multicolor' : '' }}"
                          style="{{ $color->is_multicolor ? '' : 'background:'.($color->hex ?: '#ddd') }}"
                          title="{{ $color->name }}"
                          aria-label="{{ $color->name }}"></span>
                @endforeach
                @if ($product->colors->count() > 5)
                    <span>+{{ $product->colors->count() - 5 }}</span>
                @endif
            </div>
        @endif

        <div class="card-meta">
            <span>{{ $product->shop?->locality }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ $product->shop?->pincode }}</span>
        </div>

        <div class="price-line">
            <span class="price">₹{{ \App\Support\Money::format($effective, false) }}</span>
            @if ($product->offer_price_paise)
                <span class="price-was">₹{{ \App\Support\Money::format($product->price_paise, false) }}</span>
            @endif
        </div>
        <span class="unit-note">for {{ $qty }} {{ \Illuminate\Support\Str::plural($unitLabel, (float) $qty === 1.0 ? 1 : 2) }}</span>
    </div>
</article>
