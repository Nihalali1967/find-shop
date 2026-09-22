<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Shop\Concerns\ResolvesShop;
use App\Models\Conversation;
use App\Models\Notification;
use App\Support\DataTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use ResolvesShop;

    public function index(Request $request): View
    {
        $shop = $this->currentShop($request);

        $notifications = Notification::query()
            ->where('recipient_type', 'shop')
            ->where('recipient_id', $shop->id)
            ->when($request->filled('q'), function ($query) use ($request) {
                $like = DataTable::like($request->input('q'));
                $query->where(function ($inner) use ($like) {
                    $inner->where('title', 'like', $like)->orWhere('body', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate(DataTable::perPage($request, 20))
            ->withQueryString();

        return view('shop.notifications', compact('shop', 'notifications'));
    }

    public function read(Request $request, Notification $notification): RedirectResponse
    {
        $shop = $this->currentShop($request);

        abort_unless($notification->recipient_type === 'shop' && (int) $notification->recipient_id === $shop->id, 403);

        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        $conversationId = $notification->payload['conversation_id'] ?? null;

        if ($conversationId && Conversation::where('id', $conversationId)->where('shop_id', $shop->id)->exists()) {
            return redirect()->route('shop.chat.show', $conversationId);
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $shop = $this->currentShop($request);

        Notification::query()
            ->where('recipient_type', 'shop')
            ->where('recipient_id', $shop->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
