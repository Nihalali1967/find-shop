@extends('layouts.admin')

@section('title', 'Colors')
@section('nav', 'colors')
@section('page-title', 'Colors')

@section('content')
    <div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start">
        <div class="card">
            <div class="card-head">
                <h2>Palette</h2>
                <span class="muted" style="font-size:.84rem">{{ $colors->count() }} {{ \Illuminate\Support\Str::plural('color', $colors->count()) }}</span>
            </div>

            @if ($colors->isEmpty())
                <x-empty-state title="No colors yet" glyph="◍" message="Add the first swatch with the form on the right." />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Swatch</th>
                                <th>Name</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($colors as $color)
                                @php
                                    $formId = 'color-form-'.$color->id;
                                @endphp
                                <tr>
                                    <td>
                                        <form method="POST" action="{{ route('admin.colors.update', $color) }}" id="{{ $formId }}"
                                              class="flex center gap-1 wrap-gap">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="swatch_class" value="">
                                            <input type="hidden" name="sort_order" value="{{ $color->sort_order }}">
                                            <span class="swatch {{ $color->is_multicolor ? 'swatch-multicolor' : '' }} swatch-lg"
                                                  style="{{ $color->is_multicolor ? '' : 'background:'.($color->hex ?: '#ddd') }}"
                                                  aria-hidden="true"></span>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="stack gap-1">
                                            <input form="{{ $formId }}" type="text" name="name" value="{{ $color->name }}"
                                                   maxlength="60" required style="max-width:170px" aria-label="Color name">
                                            <div class="flex center gap-1 wrap-gap" style="font-size:.8rem;color:var(--muted)">
                                                <input form="{{ $formId }}" type="color" name="hex_picker" value="{{ $color->hex ?? '#cccccc' }}"
                                                       aria-label="Pick color for {{ $color->name }}"
                                                       {{ $color->is_multicolor ? 'disabled' : '' }}
                                                       onchange="this.closest('tr').querySelector('input[name=hex]').value = this.value">
                                                <input form="{{ $formId }}" type="text" name="hex" value="{{ $color->hex }}"
                                                       pattern="#[0-9a-fA-F]{6}" placeholder="#RRGGBB" maxlength="7"
                                                       style="max-width:96px" aria-label="Hex code for {{ $color->name }}">
                                                <label class="flex center gap-1">
                                                    <input form="{{ $formId }}" type="checkbox" name="is_multicolor" value="1"
                                                           @checked($color->is_multicolor)> Multicolor
                                                </label>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $color->products_count }}</td>
                                    <td>
                                        <select form="{{ $formId }}" name="is_active" style="max-width:120px" aria-label="Status for {{ $color->name }}">
                                            <option value="1" @selected($color->is_active)>Active</option>
                                            <option value="0" @selected(! $color->is_active)>Inactive</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="table-actions" style="justify-content:flex-end">
                                            <button type="submit" form="{{ $formId }}" class="btn btn-sm btn-ghost">Save</button>
                                            <form method="POST" action="{{ route('admin.colors.destroy', $color) }}"
                                                  data-confirm="Delete “{{ $color->name }}”? Colors attached to products must be detached first — deactivating is safer.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="card-foot">
                <p class="hint mb-0">
                    Inactive colors disappear from product forms, filters and the public palette, but stay on existing listings.
                </p>
            </div>
        </div>

        <div class="stack">
            <div class="card card-pad">
                <h3>New color</h3>
                <form method="POST" action="{{ route('admin.colors.store') }}">
                    @csrf
                    <div class="field">
                        <label for="new_color_name">Name</label>
                        <input id="new_color_name" type="text" name="name" required maxlength="60" value="{{ old('name') }}">
                        <x-field-error name="name" />
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="new_color_hex">Hex code</label>
                            <div class="flex center gap-1">
                                <input id="new_color_hex" type="text" name="hex" placeholder="#RRGGBB" maxlength="7"
                                       pattern="#[0-9a-fA-F]{6}" value="{{ old('hex') }}" style="max-width:110px">
                                <input type="color" aria-label="Pick a color"
                                       value="{{ old('hex', '#4c7a5a') }}"
                                       oninput="document.getElementById('new_color_hex').value = this.value">
                            </div>
                            <x-field-error name="hex" />
                        </div>
                        <div class="field">
                            <label for="new_color_order">Sort order</label>
                            <input id="new_color_order" type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}">
                        </div>
                    </div>

                    <div class="field">
                        <label class="flex center gap-1">
                            <input type="checkbox" name="is_multicolor" value="1" @checked(old('is_multicolor'))>
                            Multicolor swatch (ignore hex)
                        </label>
                    </div>

                    <div class="field">
                        <label class="flex center gap-1">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                            Active — visible everywhere immediately
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Add color</button>
                </form>
            </div>

            <div class="card card-pad">
                <h3>In use</h3>
                <p class="hint mb-0">
                    {{ $inUseCount }} {{ \Illuminate\Support\Str::plural('product', $inUseCount) }} currently reference at least one color.
                    Deleting an attached color is blocked; deactivate it instead.
                </p>
            </div>
        </div>
    </div>
@endsection
