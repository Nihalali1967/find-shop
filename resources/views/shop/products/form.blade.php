@extends('layouts.shop')

@php
    $isEdit = $product->exists;
    $subcategoryMap = [];
    foreach ($categories as $category) {
        $subcategoryMap[$category->id] = $category->subcategories->pluck('id')->all();
    }
    $selectedColors = old('color_ids', $isEdit ? $product->colors->pluck('id')->all() : []);
    $selectedColors = array_map('intval', (array) $selectedColors);
    $rupees = fn (?int $paise) => $paise === null ? '' : number_format($paise / 100, 2, '.', '');
    $currentCategory = old('category_id', $product->subcategory?->category_id);
@endphp

@section('title', $isEdit ? 'Edit product' : 'New product')
@section('nav', 'products')
@section('page-title', $isEdit ? 'Edit product' : 'New product')

@section('content')
    <div class="grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);align-items:start">
        <div class="card card-pad">
            <form method="POST"
                  action="{{ $isEdit ? route('shop.products.update', $product) : route('shop.products.store') }}"
                  enctype="multipart/form-data">
                @csrf
                @if ($isEdit)
                    @method('PATCH')
                @endif

                <fieldset>
                    <legend>Listing details</legend>

                    <div class="field">
                        <label for="name">Product name</label>
                        <input id="name" type="text" name="name" maxlength="160" required
                               value="{{ old('name', $product->name) }}" placeholder="Italian marble">
                        <x-field-error name="name" />
                    </div>

                    <div class="field">
                        <label for="title">Title</label>
                        <input id="title" type="text" name="title" maxlength="220" required
                               value="{{ old('title', $product->title) }}" placeholder="Premium white marble for living room flooring">
                        <x-field-error name="title" />
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" maxlength="{{ config('marketplace.products.description_max') }}" required>{{ old('description', $product->description) }}</textarea>
                        <div class="hint">Plain text. It is escaped wherever it is displayed.</div>
                        <x-field-error name="description" />
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Category</legend>

                    <div class="form-grid">
                        <div class="field">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" required data-category-select>
                                <option value="">Choose a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int) $currentCategory === $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint">Changing category clears an incompatible subcategory.</div>
                            <x-field-error name="category_id" />
                        </div>

                        <div class="field">
                            <label for="subcategory_id">Subcategory</label>
                            <select id="subcategory_id" name="subcategory_id" required data-subcategory-select
                                    data-map="{{ json_encode($subcategoryMap) }}">
                                <option value="">Choose a subcategory</option>
                                @foreach ($categories as $category)
                                    @foreach ($category->subcategories as $subcategory)
                                        <option value="{{ $subcategory->id }}" @selected((int) old('subcategory_id', $product->subcategory_id) === $subcategory->id)>
                                            {{ $subcategory->name }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                            <x-field-error name="subcategory_id" />
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Pricing</legend>

                    <div class="form-grid">
                        <div class="field">
                            <label for="unit_id">Unit</label>
                            <select id="unit_id" name="unit_id" required data-unit-select>
                                <option value="">Choose a unit</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}"
                                            data-requires-custom="{{ $unit->requires_custom ? '1' : '0' }}"
                                            @selected((int) old('unit_id', $product->unit_id) === $unit->id)>
                                        {{ $unit->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-field-error name="unit_id" />
                        </div>

                        <div class="field">
                            <label for="unit_count">Quantity for this price</label>
                            <input id="unit_count" type="number" name="unit_count" step="0.001" min="0.001" required
                                   value="{{ old('unit_count', \App\Support\Money::quantity($product->unit_count)) }}">
                            <div class="hint">For example 2, when ₹900 buys two metres.</div>
                            <x-field-error name="unit_count" />
                        </div>

                        <div class="field" data-custom-unit-field hidden>
                            <label for="custom_unit">Custom unit name</label>
                            <input id="custom_unit" type="text" name="custom_unit" maxlength="40"
                                   value="{{ old('custom_unit', $product->custom_unit) }}" placeholder="e.g. running foot">
                            <x-field-error name="custom_unit" />
                        </div>

                        <div class="field">
                            <label for="price">Price (₹)</label>
                            <input id="price" data-price-input type="number" name="price" step="0.01" min="0.01" required
                                   value="{{ old('price', $rupees($product->price_paise)) }}">
                            <x-field-error name="price" />
                        </div>

                        <div class="field">
                            <label for="offer_price">Offer price (₹) <span class="opt">(optional)</span></label>
                            <input id="offer_price" data-offer-input type="number" name="offer_price" step="0.01" min="0.01"
                                   value="{{ old('offer_price', $rupees($product->offer_price_paise)) }}">
                            <div class="field-error" data-offer-warning hidden>Offer price must be lower than the regular price.</div>
                            <x-field-error name="offer_price" />
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Colours</legend>
                    <div class="chip-row">
                        @foreach ($colors as $color)
                            <label class="chip {{ in_array($color->id, $selectedColors, true) ? 'is-on' : '' }}">
                                <input type="checkbox" name="color_ids[]" value="{{ $color->id }}"
                                       @checked(in_array($color->id, $selectedColors, true))>
                                <span class="swatch {{ $color->is_multicolor ? 'swatch-multicolor' : '' }}"
                                      style="{{ $color->is_multicolor ? '' : 'background:'.($color->hex ?: '#ddd') }}"
                                      aria-hidden="true"></span>
                                <span>{{ $color->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-field-error name="color_ids" />
                </fieldset>

                @unless ($isEdit)
                    <fieldset>
                        <legend>Images</legend>
                        <div class="field">
                            <label for="images">Upload 1–{{ config('marketplace.products.max_images') }} images</label>
                            <input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp"
                                   multiple data-preview="#image-preview">
                            <div class="hint">JPEG, PNG or WebP, up to {{ config('marketplace.products.image_max_kb') / 1024 }} MB each. The first image becomes the cover.</div>
                            <x-field-error name="images" />
                        </div>
                        <div class="image-tiles mt-2" id="image-preview"></div>
                    </fieldset>
                @endunless

                <fieldset>
                    <legend>Publication</legend>
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="draft" @selected(old('status', $product->status) === 'draft')>Draft — hidden from buyers</option>
                            <option value="published" @selected(old('status', $product->status) === 'published')>Published — visible in catalog</option>
                        </select>
                        <div class="hint">Publishing needs a cover image and every required field.</div>
                        <x-field-error name="status" />
                    </div>
                </fieldset>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary btn-lg">{{ $isEdit ? 'Save changes' : 'Create product' }}</button>
                    <a href="{{ route('shop.products.index') }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>

        <div class="stack">
            @if ($isEdit)
                <div class="card card-pad">
                    <h3>Images</h3>
                    <p class="hint">The first image is the cover. Use the arrows to reorder, then save the order.</p>

                    @if ($product->images->isEmpty())
                        <div class="alert alert-warn">No images yet. A published product needs at least one.</div>
                    @else
                        <form method="POST" action="{{ route('shop.products.images.order', $product) }}">
                            @csrf
                            @method('PATCH')
                            <div class="image-tiles" data-image-order>
                                @foreach ($product->images as $index => $image)
                                    <div class="image-tile">
                                        <img src="{{ $image->thumbUrl() }}" alt="">
                                        @if ($index === 0)
                                            <span class="tag">Cover</span>
                                        @endif
                                        <input type="hidden" name="order[]" value="{{ $image->id }}">
                                        <span style="position:absolute;bottom:6px;left:6px;display:flex;gap:4px">
                                            <button type="button" data-move="up" class="drop" style="position:static;right:auto" aria-label="Move earlier">↑</button>
                                            <button type="button" data-move="down" class="drop" style="position:static;right:auto" aria-label="Move later">↓</button>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-ghost btn-sm mt-2">Save image order</button>
                        </form>

                        <div class="mt-2">
                            @foreach ($product->images as $image)
                                <form method="POST" action="{{ route('shop.products.images.destroy', [$product, $image]) }}"
                                      data-confirm="Remove this image? A published product must keep at least one.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger btn-block">
                                        Remove image {{ $image->sort_order }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('shop.products.images.store', $product) }}"
                          enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <div class="field">
                            <label for="more_images">Add more images</label>
                            <input id="more_images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
                        </div>
                        <button type="submit" class="btn btn-ghost btn-sm">Upload</button>
                    </form>
                </div>
            @endif

            <div class="card card-pad">
                <h3>Checklist</h3>
                <ul class="spec-list">
                    <li><span class="k">Name</span><span>Short material name</span></li>
                    <li><span class="k">Title</span><span>Buyer-facing summary</span></li>
                    <li><span class="k">Category</span><span>Category and matching subcategory</span></li>
                    <li><span class="k">Price</span><span>Amount plus the quantity it covers</span></li>
                    <li><span class="k">Colours</span><span>At least one palette colour</span></li>
                    <li><span class="k">Cover</span><span>First ordered image</span></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
