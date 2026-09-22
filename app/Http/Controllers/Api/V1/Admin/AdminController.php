<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvitation;
use App\Models\Subcategory;
use App\Services\Audit\AuditLogger;
use App\Services\Shops\ShopService;
use App\Support\NameNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $shops = Shop::query();

        return response()->json([
            'data' => [
                'shops_total' => (clone $shops)->count(),
                'shops_active' => (clone $shops)->where('status', Shop::STATUS_ACTIVE)->count(),
                'shops_inactive' => (clone $shops)->where('status', Shop::STATUS_INACTIVE)->count(),
                'shops_suspended' => (clone $shops)->where('status', Shop::STATUS_SUSPENDED)->count(),
                'products_total' => Product::count(),
                'products_published' => Product::where('status', Product::STATUS_PUBLISHED)->count(),
                'pending_invitations' => ShopInvitation::whereNull('claimed_at')->count(),
            ],
        ]);
    }

    public function shops(Request $request): JsonResponse
    {
        $shops = Shop::query()
            ->with('owner')
            ->withCount('products')
            ->when($request->input('q'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => $shops->getCollection()->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'locality' => $shop->locality,
                'pincode' => $shop->pincode,
                'status' => $shop->status,
                'owner_phone' => $shop->owner?->phone,
                'products_count' => $shop->products_count,
            ]),
            'meta' => [
                'current_page' => $shops->currentPage(),
                'last_page' => $shops->lastPage(),
                'total' => $shops->total(),
            ],
        ]);
    }

    public function shop(Shop $shop): JsonResponse
    {
        $shop->load('statusEvents');

        return response()->json(['data' => [
            'id' => $shop->id,
            'name' => $shop->name,
            'locality' => $shop->locality,
            'pincode' => $shop->pincode,
            'address' => $shop->address,
            'status' => $shop->status,
            'owner_phone' => $shop->owner?->phone,
            'status_history' => $shop->statusEvents->map(fn ($e) => [
                'from' => $e->from_status,
                'to' => $e->to_status,
                'reason' => $e->reason,
                'at' => $e->created_at?->toIso8601String(),
            ]),
        ]]);
    }

    public function updateShop(Request $request, Shop $shop): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'locality' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'address' => ['required', 'string', 'max:400'],
        ]);

        $shop->fill($data)->save();

        AuditLogger::log('shop.updated', subject: $shop, actor: $request->user(), meta: $data, ip: $request->ip());

        return response()->json(['data' => $shop->only(['id', 'name', 'locality', 'pincode', 'address'])]);
    }

    public function shopStatus(Request $request, Shop $shop, ShopService $service): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Shop::STATUSES)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $service->changeStatus($shop, $data['status'], $data['reason'], $request->user());

        AuditLogger::log('shop.status_changed', subject: $shop, actor: $request->user(), meta: $data, ip: $request->ip());

        return response()->json(['data' => ['id' => $shop->id, 'status' => $shop->refresh()->status]]);
    }

    public function deleteShop(Request $request, Shop $shop): JsonResponse
    {
        AuditLogger::log('shop.deleted', subject: $shop, actor: $request->user(), ip: $request->ip());
        $shop->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeInvitation(Request $request, ShopService $shops): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'intended_phone' => ['required', 'string', 'max:20'],
            'locality' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'address' => ['required', 'string', 'max:400'],
            'expires_in_days' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        if (! $shops->isNameAvailable($data['name'])) {
            throw ValidationException::withMessages(['name' => 'That shop name is already taken.']);
        }

        $token = Str::random(64);

        $invitation = ShopInvitation::create([
            'name' => NameNormalizer::normalize($data['name']),
            'name_key' => NameNormalizer::key($data['name']),
            'intended_phone' => NameNormalizer::phone($data['intended_phone']),
            'claim_token_hash' => hash('sha256', $token),
            'profile' => ['locality' => $data['locality'], 'pincode' => $data['pincode'], 'address' => $data['address']],
            'created_by_admin_id' => $request->user()->id,
            'expires_at' => now()->addDays((int) $data['expires_in_days']),
        ]);

        return response()->json([
            'data' => ['id' => $invitation->id, 'claim_url' => route('shop.claim.show', $token)],
        ], 201);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::withCount('subcategories')->orderBy('sort_order')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'subcategories_count' => $c->subcategories_count])]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $category = Category::create(['name' => $data['name'], 'slug' => NameNormalizer::slug($data['name'])]);

        return response()->json(['data' => ['id' => $category->id, 'name' => $category->name]], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $category->update($data);

        return response()->json(['data' => ['id' => $category->id, 'name' => $category->name, 'status' => $category->status]]);
    }

    public function deleteCategory(Category $category): JsonResponse
    {
        $ids = Subcategory::where('category_id', $category->id)->pluck('id');

        if (Product::whereIn('subcategory_id', $ids)->exists()) {
            return response()->json(['message' => 'Reassign products before deleting this category.', 'code' => 'CATEGORY_IN_USE'], 409);
        }

        $category->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeSubcategory(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => $data['name'],
            'slug' => NameNormalizer::slug($data['name']),
        ]);

        return response()->json(['data' => ['id' => $subcategory->id, 'name' => $subcategory->name]], 201);
    }

    public function updateSubcategory(Request $request, Subcategory $subcategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $subcategory->update($data);

        return response()->json(['data' => ['id' => $subcategory->id, 'name' => $subcategory->name, 'status' => $subcategory->status]]);
    }

    public function deleteSubcategory(Request $request, Subcategory $subcategory): JsonResponse
    {
        $reassignTo = $request->integer('reassign_to');

        if (Product::where('subcategory_id', $subcategory->id)->exists() && ! $reassignTo) {
            return response()->json(['message' => 'Reassign products before deleting this subcategory.', 'code' => 'SUBCATEGORY_IN_USE'], 409);
        }

        if ($reassignTo) {
            DB::transaction(fn () => Product::where('subcategory_id', $subcategory->id)->update(['subcategory_id' => $reassignTo]));
        }

        $subcategory->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
