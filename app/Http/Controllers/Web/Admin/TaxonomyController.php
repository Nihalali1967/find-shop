<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Services\Audit\AuditLogger;
use App\Support\DataTable;
use App\Support\NameNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaxonomyController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->withCount('subcategories')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', DataTable::like($request->input('q'))))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selected = $request->integer('category_id')
            ? Category::find($request->integer('category_id'))
            : $categories->first();

        $sort = DataTable::sortable($request, ['name', 'products_count', 'sort_order']);
        $direction = DataTable::direction($request);

        return view('admin.taxonomy.index', [
            'categories' => $categories,
            'selected' => $selected,
            'subcategories' => $selected
                ? Subcategory::query()
                    ->where('category_id', $selected->id)
                    ->withCount('products')
                    ->when($request->filled('sq'), fn ($query) => $query->where('name', 'like', DataTable::like($request->input('sq'))))
                    ->when($sort === 'name', fn ($query) => $query->orderBy('name', $direction))
                    ->when($sort === 'products_count', fn ($query) => $query->orderBy('products_count', $direction))
                    ->when($sort === 'sort_order', fn ($query) => $query->orderBy('sort_order', $direction))
                    ->when($sort === null, fn ($query) => $query->orderBy('sort_order'))
                    ->orderBy('name')
                    ->paginate(DataTable::perPage($request, 10))
                    ->withQueryString()
                : null,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $this->assertUniqueCategory($data['name']);

        $category = Category::create([
            'name' => NameNormalizer::normalize($data['name']),
            'slug' => NameNormalizer::slug($data['name']),
            'status' => 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        AuditLogger::log('category.created', subject: $category, actor: $request->user('admin'), ip: $request->ip());

        return back()->with('status', 'Category created.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $this->assertUniqueCategory($data['name'], $category->id);

        $category->update([
            'name' => NameNormalizer::normalize($data['name']),
            'status' => $data['status'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        AuditLogger::log('category.updated', subject: $category, actor: $request->user('admin'),
            meta: ['status' => $data['status']], ip: $request->ip());

        return back()->with('status', 'Category updated.');
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        $subcategoryIds = Subcategory::where('category_id', $category->id)->pluck('id');
        $productCount = Product::whereIn('subcategory_id', $subcategoryIds)->count();

        if ($productCount > 0) {
            throw ValidationException::withMessages([
                'category' => "Reassign the {$productCount} product(s) in this category before deleting it.",
            ]);
        }

        AuditLogger::log('category.deleted', subject: $category, actor: $request->user('admin'), ip: $request->ip());
        $category->delete();

        return redirect()->route('admin.taxonomy.index')->with('status', 'Category deleted.');
    }

    public function storeSubcategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $this->assertUniqueSubcategory($category->id, $data['name']);

        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => NameNormalizer::normalize($data['name']),
            'slug' => NameNormalizer::slug($data['name']),
            'status' => 'active',
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        AuditLogger::log('subcategory.created', subject: $subcategory, actor: $request->user('admin'), ip: $request->ip());

        return back()->with('status', 'Subcategory created.');
    }

    public function updateSubcategory(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $this->assertUniqueSubcategory($subcategory->category_id, $data['name'], $subcategory->id);

        $subcategory->update([
            'name' => NameNormalizer::normalize($data['name']),
            'status' => $data['status'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        AuditLogger::log('subcategory.updated', subject: $subcategory, actor: $request->user('admin'),
            meta: ['status' => $data['status']], ip: $request->ip());

        return back()->with('status', 'Subcategory updated.');
    }

    public function destroySubcategory(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $productCount = Product::where('subcategory_id', $subcategory->id)->count();

        if ($productCount > 0) {
            $reassignTo = $request->integer('reassign_to');

            if (! $reassignTo) {
                throw ValidationException::withMessages([
                    'subcategory' => "Reassign the {$productCount} product(s) before deleting, or choose a replacement subcategory.",
                ]);
            }

            $target = Subcategory::find($reassignTo);

            if (! $target || $target->id === $subcategory->id) {
                throw ValidationException::withMessages(['reassign_to' => 'Choose a different subcategory to move products into.']);
            }

            DB::transaction(function () use ($subcategory, $target) {
                Product::where('subcategory_id', $subcategory->id)->update(['subcategory_id' => $target->id]);
            });
        }

        AuditLogger::log('subcategory.deleted', subject: $subcategory, actor: $request->user('admin'), ip: $request->ip());
        $subcategory->delete();

        return back()->with('status', 'Subcategory deleted.');
    }

    protected function assertUniqueCategory(string $name, ?int $exceptId = null): void
    {
        $key = NameNormalizer::key($name);

        $exists = Category::where('name_key', $key)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'A category with that name already exists.']);
        }
    }

    protected function assertUniqueSubcategory(int $categoryId, string $name, ?int $exceptId = null): void
    {
        $key = NameNormalizer::key($name);

        $exists = Subcategory::where('category_id', $categoryId)
            ->where('name_key', $key)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'That subcategory already exists in this category.']);
        }
    }
}
