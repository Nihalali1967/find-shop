<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Shops\ShopService;
use App\Support\DataTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $sort = DataTable::sortable($request, ['name', 'products_count', 'status', 'created_at']);
        $direction = DataTable::direction($request);

        $shops = Shop::query()
            ->with('owner')
            ->withCount('products')
            ->when($request->input('q'), function ($query, $q) {
                $like = DataTable::like($q);
                $query->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('locality', 'like', $like)
                        ->orWhere('pincode', 'like', $like)
                        ->orWhereHas('owner', fn ($o) => $o->where('phone', 'like', $like));
                });
            })
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('locality'), fn ($query, $locality) => $query->where('locality', 'like', DataTable::like($locality)))
            ->when($sort === 'name', fn ($query) => $query->orderBy('name', $direction))
            ->when($sort === 'products_count', fn ($query) => $query->orderBy('products_count', $direction))
            ->when($sort === 'status', fn ($query) => $query->orderBy('status', $direction))
            ->when($sort === 'created_at', fn ($query) => $query->orderBy('created_at', $direction))
            ->latest('id')
            ->paginate(DataTable::perPage($request, 15))
            ->withQueryString();

        return view('admin.shops.index', [
            'shops' => $shops,
            'statuses' => Shop::STATUSES,
        ]);
    }

    public function show(Shop $shop): View
    {
        $shop->load(['owner', 'statusEvents']);

        return view('admin.shops.show', [
            'shop' => $shop,
            'products' => $shop->products()->with(['images', 'subcategory.category', 'unit'])->latest('id')->paginate(10),
            'statuses' => Shop::STATUSES,
        ]);
    }

    public function update(Request $request, Shop $shop, ShopService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'locality' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'address' => ['required', 'string', 'max:400'],
            'gst_number' => ['nullable', 'string', 'max:15'],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        $before = $shop->only(['name', 'locality', 'pincode']);

        // Ownership is never editable here: a phone/contact edit cannot reassign a shop.
        $shop->fill($data)->save();

        AuditLogger::log('shop.updated', subject: $shop, actor: $request->user('admin'),
            meta: ['before' => $before, 'after' => $shop->only(['name', 'locality', 'pincode'])],
            ip: $request->ip());

        return back()->with('status', 'Shop profile updated.');
    }

    public function status(Request $request, Shop $shop, ShopService $service, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $service->changeStatus($shop, $data['status'], $data['reason'], $request->user('admin'));

        $notifications->push(
            'shop',
            $shop->id,
            "status:{$shop->status}:".now()->timestamp,
            'shop_status',
            'Your shop is now '.$shop->status,
            $data['reason'],
            ['status' => $shop->status],
        );

        AuditLogger::log('shop.status_changed', subject: $shop, actor: $request->user('admin'),
            meta: ['status' => $data['status'], 'reason' => $data['reason']], ip: $request->ip());

        return back()->with('status', "Shop marked {$data['status']}.");
    }

    public function destroy(Request $request, Shop $shop): RedirectResponse
    {
        AuditLogger::log('shop.deleted', subject: $shop, actor: $request->user('admin'), ip: $request->ip());

        $shop->delete();

        return redirect()->route('admin.shops.index')->with('status', 'Shop deleted. Historical chat is retained.');
    }
}
