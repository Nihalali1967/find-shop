<?php

namespace App\Http\Controllers\Web\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Shop\Concerns\ResolvesShop;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    use ResolvesShop;

    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request): View
    {
        $shop = $this->currentShop($request);

        $conversations = Conversation::query()
            ->where('shop_id', $shop->id)
            ->with(['client', 'product', 'latestMessage', 'participants'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(15);

        $unread = [];

        foreach ($conversations as $conversation) {
            $participant = $conversation->participantFor('shop', $shop->id);
            $unread[$conversation->id] = $participant ? $this->chat->unreadCount($conversation, $participant) : 0;
        }

        return view('shop.chat.index', ['shop' => $shop, 'conversations' => $conversations, 'unread' => $unread]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $shop = $this->currentShop($request);
        $this->assertOwns($conversation, $shop->id);

        $this->chat->markRead($conversation, 'shop', $shop->id, null);

        $conversation->load(['client', 'product.images', 'participants']);

        return view('shop.chat.show', [
            'shop' => $shop,
            'conversation' => $conversation,
            'messages' => $conversation->messages()->paginate(30),
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $shop = $this->currentShop($request);
        $this->assertOwns($conversation, $shop->id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('marketplace.chat.max_message_length')],
            'client_message_id' => ['nullable', 'string', 'max:64'],
        ]);

        $message = $this->chat->sendMessage(
            $conversation,
            'shop',
            $shop->id,
            $data['body'],
            $data['client_message_id'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->payload($message)]);
        }

        return back();
    }

    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $shop = $this->currentShop($request);
        $this->assertOwns($conversation, $shop->id);

        $messages = $conversation->messages()
            ->where('id', '>', $request->integer('after', 0))
            ->limit(100)
            ->get()
            ->map(fn (Message $m) => $this->payload($m));

        return response()->json([
            'data' => $messages,
            'meta' => ['latest_id' => (int) $conversation->messages()->max('id')],
        ]);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $shop = $this->currentShop($request);
        $this->assertOwns($conversation, $shop->id);

        $participant = $this->chat->markRead($conversation, 'shop', $shop->id, $request->integer('message_id') ?: null);

        return response()->json(['data' => ['last_read_message_id' => $participant->last_read_message_id]]);
    }

    protected function assertOwns(Conversation $conversation, int $shopId): void
    {
        abort_unless((int) $conversation->shop_id === $shopId, 403);
    }

    protected function payload(Message $message): array
    {
        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'body' => $message->body,
            'client_message_id' => $message->client_message_id,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
