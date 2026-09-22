<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ProductSearchProjector;
use App\Support\NameNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ColorController extends Controller
{
    public function index(): View
    {
        $colors = Color::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.colors.index', [
            'colors' => $colors,
            'inUseCount' => Product::whereHas('colors')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, creating: true);

        $this->assertUniqueName($data['name']);

        $color = Color::create([
            'name' => NameNormalizer::normalize($data['name']),
            'hex' => $data['hex'] ?? null,
            'swatch_class' => $this->swatchClass($data),
            'is_multicolor' => $data['is_multicolor'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        AuditLogger::log('color.created', subject: $color, actor: $request->user('admin'), ip: $request->ip());

        return back()->with('status', 'Color created.');
    }

    public function update(Request $request, Color $color): RedirectResponse
    {
        $data = $this->validated($request, creating: false);

        $this->assertUniqueName($data['name'], $color->id);

        $originalNameKey = $color->name_key;

        $color->update([
            'name' => NameNormalizer::normalize($data['name']),
            'hex' => $data['hex'] ?? null,
            'swatch_class' => $this->swatchClass($data),
            'is_multicolor' => $data['is_multicolor'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        // Renaming a color changes the search vocabulary, so keep projections fresh.
        if ($originalNameKey !== $color->name_key) {
            $this->refreshSearchDocuments($color);
        }

        AuditLogger::log('color.updated', subject: $color, actor: $request->user('admin'),
            meta: ['status' => $data['is_active'] ? 'active' : 'inactive', 'name' => $color->name],
            ip: $request->ip());

        return back()->with('status', 'Color updated.');
    }

    public function destroy(Request $request, Color $color): RedirectResponse
    {
        $productCount = $color->products()->count();

        if ($productCount > 0) {
            throw ValidationException::withMessages([
                'color' => "Reassign the {$productCount} product(s) using this color before deleting it. Deactivating it is safer.",
            ]);
        }

        AuditLogger::log('color.deleted', subject: $color, actor: $request->user('admin'), ip: $request->ip());
        $color->delete();

        return back()->with('status', 'Color deleted.');
    }

    /**
     * @return array{name:string,hex:?string,is_multicolor:bool,is_active:bool,sort_order:int}
     */
    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'swatch_class' => ['nullable', Rule::in($this->presetSwatches())],
            'is_multicolor' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['is_multicolor'] = (bool) ($data['is_multicolor'] ?? false);

        if (! array_key_exists('is_active', $data) || $data['is_active'] === null) {
            $data['is_active'] = false;
        }

        $data['sort_order'] ??= 0;

        // A multicolor swatch is inherently patterned; a hex value is meaningless there.
        if ($data['is_multicolor']) {
            $data['hex'] = null;
            $data['swatch_class'] ??= 'swatch-multicolor';
        } elseif ($creating && ! ($data['hex'] ?? null) && ! ($data['swatch_class'] ?? null)) {
            throw ValidationException::withMessages([
                'hex' => 'Provide a hex code or choose a preset swatch.',
            ]);
        }

        return $data;
    }

    private function swatchClass(array $data): ?string
    {
        return $data['is_multicolor'] ? 'swatch-multicolor' : ($data['swatch_class'] ?? null);
    }

    /**
     * @return array<int,string>
     */
    private function presetSwatches(): array
    {
        return ['swatch-white', 'swatch-black', 'swatch-gray', 'swatch-silver', 'swatch-gold',
            'swatch-transparent', 'swatch-beige', 'swatch-brown', 'swatch-red', 'swatch-orange',
            'swatch-yellow', 'swatch-green', 'swatch-blue', 'swatch-purple', 'swatch-pink',
            'swatch-multicolor'];
    }

    private function assertUniqueName(string $name, ?int $exceptId = null): void
    {
        $key = NameNormalizer::key($name);

        $exists = Color::where('name_key', $key)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'A color with that name already exists.']);
        }
    }

    private function refreshSearchDocuments(Color $color): void
    {
        DB::transaction(function () use ($color) {
            $color->products()->chunkById(200, function ($products) {
                $projector = app(ProductSearchProjector::class);

                foreach ($products as $product) {
                    $projector->sync($product);
                }
            });
        });
    }
}
