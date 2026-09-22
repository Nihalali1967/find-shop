<?php

namespace App\Http\Requests;

use App\Models\Subcategory;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Authorize before validating so a foreign product returns 403, not a
     * validation error that leaks the shape of another shop's listing.
     */
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        $product = $this->route('product');

        if ($product instanceof \App\Models\Product) {
            return $this->user()->can('update', $product);
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('marketplace.products.image_max_kb');
        $maxImages = (int) config('marketplace.products.max_images');
        $maxDescription = (int) config('marketplace.products.description_max');

        return [
            'name' => ['required', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:220'],
            'description' => ['required', 'string', 'max:'.$maxDescription],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', 'active')],
            'subcategory_id' => ['required', 'integer', Rule::exists('subcategories', 'id')->where('status', 'active')],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'custom_unit' => ['nullable', 'string', 'max:40'],
            'unit_count' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'offer_price' => ['nullable', 'numeric', 'gt:0', 'lt:price'],
            'color_ids' => ['required', 'array', 'min:1'],
            'color_ids.*' => ['integer', 'exists:colors,id'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'images' => ['sometimes', 'array', 'max:'.$maxImages],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $categoryId = (int) $this->input('category_id');
            $subcategoryId = (int) $this->input('subcategory_id');

            $subcategory = $subcategoryId ? Subcategory::find($subcategoryId) : null;

            if ($subcategory && (int) $subcategory->category_id !== $categoryId) {
                $validator->errors()->add('subcategory_id', 'That subcategory does not belong to the selected category.');
            }

            $unit = $this->input('unit_id') ? Unit::find((int) $this->input('unit_id')) : null;

            if ($unit && $unit->requires_custom && trim((string) $this->input('custom_unit')) === '') {
                $validator->errors()->add('custom_unit', 'Describe the custom unit.');
            }
        });
    }

    /**
     * Normalized attribute array with integer paise values.
     *
     * @return array<string,mixed>
     */
    public function productData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'subcategory_id' => (int) $validated['subcategory_id'],
            'unit_id' => (int) $validated['unit_id'],
            'custom_unit' => $validated['custom_unit'] ?? null,
            'unit_count' => $validated['unit_count'],
            'price_paise' => (int) Money::toPaise($validated['price']),
            'offer_price_paise' => isset($validated['offer_price']) && $validated['offer_price'] !== null
                ? (int) Money::toPaise($validated['offer_price'])
                : null,
            'status' => $validated['status'],
            'color_ids' => $validated['color_ids'],
        ];
    }
}
