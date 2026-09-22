<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvitation;
use App\Models\Subcategory;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $shops = Shop::query();

        $stats = [
            'shops' => (clone $shops)->count(),
            'active' => (clone $shops)->where('status', Shop::STATUS_ACTIVE)->count(),
            'inactive' => (clone $shops)->where('status', Shop::STATUS_INACTIVE)->count(),
            'suspended' => (clone $shops)->where('status', Shop::STATUS_SUSPENDED)->count(),
            'products' => Product::count(),
            'published' => Product::where('status', Product::STATUS_PUBLISHED)->count(),
            'categories' => Category::count(),
            'subcategories' => Subcategory::count(),
            'invitations' => ShopInvitation::whereNull('claimed_at')->count(),
        ];

        $recentShops = Shop::with('owner')->latest('id')->limit(6)->get();
        $recentAudit = AuditLog::latest('id')->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recentShops', 'recentAudit'));
    }
}
