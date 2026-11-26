<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductPopularity
{
    /** One buyer enquiry (chat) counts as much as this many views. */
    public const ENQUIRY_WEIGHT = 5;

    /**
     * Count a product view, at most once per viewer per product per day.
     */
    public function recordView(Product $product, Request $request): void
    {
        $today = now()->toDateString();
        $viewer = sha1($request->ip().'|'.$request->userAgent());

        if (! Cache::add("product-view:{$product->id}:{$viewer}:{$today}", 1, now()->endOfDay())) {
            return;
        }

        DB::table('product_view_counts')->upsert(
            [['product_id' => $product->id, 'date' => $today, 'views' => 1]],
            ['product_id', 'date'],
            ['views' => DB::raw('views + 1')]
        );
    }

    /**
     * Visible products ordered by popularity score over the last $days days.
     */
    public function query(int $days = 30): Builder
    {
        $since = now()->subDays($days);

        return Product::query()->visible()
            ->with(['shop', 'subcategory.category', 'unit', 'colors', 'images'])
            ->withCount(['conversations as enquiries_count' => fn ($q) => $q->where('created_at', '>=', $since)])
            ->addSelect(['views_count' => DB::table('product_view_counts')
                ->selectRaw('COALESCE(SUM(views), 0)')
                ->whereColumn('product_view_counts.product_id', 'products.id')
                ->where('date', '>=', $since->toDateString()),
            ])
            ->orderByRaw('(enquiries_count * '.self::ENQUIRY_WEIGHT.' + views_count) DESC')
            ->orderByDesc('products.created_at')
            ->orderByDesc('products.id');
    }
}
