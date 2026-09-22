<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Shop\Concerns\ResolvesShop;
use App\Models\Conversation;
use App\Models\Product;
use App\Services\Chat\ChatService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesShop;

    public function index(Request $request, ChatService $chat): View
    {
        $shop = $this->currentShop($request);

        $counts = [
            'products' => Product::ownedBy($shop->id)->count(),
            'published' => Product::ownedBy($shop->id)->where('status', Product::STATUS_PUBLISHED)->count(),
            'draft' => Product::ownedBy($shop->id)->where('status', Product::STATUS_DRAFT)->count(),
            'conversations' => Conversation::where('shop_id', $shop->id)->count(),
        ];

        $participantRows = Conversation::where('shop_id', $shop->id)
            ->with('participants')
            ->get();

        $counts['unread_conversations'] = $participantRows
            ->filter(function (Conversation $conversation) use ($chat, $shop) {
                $participant = $conversation->participantFor('shop', $shop->id);

                return $participant && $chat->unreadCount($conversation, $participant) > 0;
            })
            ->count();

        $recentProducts = Product::ownedBy($shop->id)
            ->with(['images', 'unit', 'subcategory.category'])
            ->latest('id')
            ->limit(5)
            ->get();

        $recentConversations = Conversation::where('shop_id', $shop->id)
            ->with(['client', 'product', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();

        return view('shop.dashboard', compact('shop', 'counts', 'recentProducts', 'recentConversations'));
    }
}
