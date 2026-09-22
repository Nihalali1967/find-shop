@extends('layouts.admin')

@section('title', 'Categories')
@section('nav', 'taxonomy')
@section('page-title', 'Categories &amp; subcategories')

@section('content')
    <div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);align-items:start">
        <div class="stack">
            <div class="card">
                <div class="card-head"><h2>Categories</h2></div>
                @if ($categories->isEmpty())
                    <x-empty-state title="No categories" glyph="⌗" message="Create the first category below." />
                @else
                    <div>
                        @foreach ($categories as $category)
                            <a class="chat-list-item {{ $selected && $selected->id === $category->id ? 'is-active' : '' }}"
                               href="{{ route('admin.taxonomy.index', ['category_id' => $category->id]) }}">
                                <div class="row">
                                    <span class="who">{{ $category->name }}</span>
                                    <x-status-badge :status="$category->status" />
                                </div>
                                <div class="prev">{{ $category->subcategories_count }} {{ \Illuminate\Support\Str::plural('subcategory', $category->subcategories_count) }}</div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card card-pad">
                <h3>New category</h3>
                <form method="POST" action="{{ route('admin.categories.store') }}">
                    @csrf
                    <div class="field">
                        <label for="new_category">Name</label>
                        <input id="new_category" type="text" name="name" required maxlength="80" value="{{ old('name') }}">
                        <x-field-error name="name" />
                    </div>
                    <div class="field">
                        <label for="new_category_order">Sort order</label>
                        <input id="new_category_order" type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Add category</button>
                </form>
            </div>
        </div>

        <div class="stack">
            @if ($selected)
                <div class="card card-pad">
                    <div class="flex between center wrap-gap">
                        <h2 class="mb-0">{{ $selected->name }}</h2>
                        <x-status-badge :status="$selected->status" />
                    </div>

                    <form method="POST" action="{{ route('admin.categories.update', $selected) }}" class="mt-2">
                        @csrf
                        @method('PATCH')
                        <div class="form-grid">
                            <div class="field">
                                <label for="cat_name">Name</label>
                                <input id="cat_name" type="text" name="name" value="{{ $selected->name }}" required maxlength="80">
                            </div>
                            <div class="field">
                                <label for="cat_status">Status</label>
                                <select id="cat_status" name="status" required>
                                    <option value="active" @selected($selected->status === 'active')>Active</option>
                                    <option value="inactive" @selected($selected->status === 'inactive')>Inactive</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="cat_order">Sort order</label>
                                <input id="cat_order" type="number" name="sort_order" min="0" value="{{ $selected->sort_order }}">
                            </div>
                        </div>
                        <div class="btn-row">
                            <button type="submit" class="btn btn-primary btn-sm">Save category</button>
                        </div>
                    </form>

                    <p class="hint mt-2">Deactivating a category also hides its products from the public catalog.</p>

                    <form method="POST" action="{{ route('admin.categories.destroy', $selected) }}"
                          data-confirm="Delete “{{ $selected->name }}”? Categories with products must be reassigned first.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete category</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h2>Subcategories</h2>
                        <span class="muted" style="font-size:.84rem">{{ $subcategories->count() }} total</span>
                    </div>

                    <div class="card-body">
                        @if ($subcategories->isEmpty())
                            <p class="muted">No subcategories yet. Add the first one below.</p>
                        @else
                            <div class="table-wrap">
                                <table class="table">
                                    <thead><tr><th>Name</th><th>Products</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                                    <tbody>
                                        @foreach ($subcategories as $subcategory)
                                            <tr>
                                                <td>
                                                    <form method="POST" action="{{ route('admin.subcategories.update', $subcategory) }}" id="sub-{{ $subcategory->id }}" class="flex center gap-1 wrap-gap">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="text" name="name" value="{{ $subcategory->name }}" maxlength="80" required style="max-width:190px">
                                                        <select name="status" style="max-width:120px">
                                                            <option value="active" @selected($subcategory->status === 'active')>Active</option>
                                                            <option value="inactive" @selected($subcategory->status === 'inactive')>Inactive</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>{{ $subcategory->products_count }}</td>
                                                <td><x-status-badge :status="$subcategory->status" /></td>
                                                <td>
                                                    <div class="table-actions" style="justify-content:flex-end">
                                                        <button type="submit" form="sub-{{ $subcategory->id }}" class="btn btn-sm btn-ghost">Save</button>
                                                        <form method="POST" action="{{ route('admin.subcategories.destroy', $subcategory) }}"
                                                              data-confirm="Delete “{{ $subcategory->name }}”? Products must be reassigned if any exist.">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="reassign_to" value="">
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
                    </div>

                    <div class="card-foot">
                        <form method="POST" action="{{ route('admin.subcategories.store', $selected) }}" class="flex center gap-1 wrap-gap">
                            @csrf
                            <input type="text" name="name" placeholder="New subcategory name" required maxlength="80" style="max-width:280px">
                            <button type="submit" class="btn btn-accent btn-sm">Add subcategory</button>
                        </form>
                        <p class="hint mt-2 mb-0">
                            Deleting a subcategory with products requires a replacement — reassign products first, then delete.
                        </p>
                    </div>
                </div>
            @else
                <x-empty-state title="Create a category to begin" glyph="⌗"
                    message="Categories group subcategories, and products attach to a subcategory." />
            @endif
        </div>
    </div>
@endsection
