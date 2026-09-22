<?php

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function start(Request $request, Product $product): RedirectResponse
    {
        if (! Auth::check()) {
            // Preserve only a validated product intent, never an arbitrary URL.
            $request->session()->put('chat_intent', $product->id);

            return redirect()->route('client.login')->with('status', 'Verify your mobile number to chat with this shop.');
        }

        $visible = Product::query()->visible()->whereKey($product->id)->exists();

        abort_unless($visible, 404);

        $product->loadMissing('images');

        $conversation = $this->chat->getOrCreate($request->user(), $product);

        return redirect()->route('client.chat.show', $conversation);
    }

    public function index(Request $request): View
    {
        $conversations = Conversation::query()
            ->where('client_id', $request->user()->id)
            ->with(['shop', 'product', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(15);

        $unread = $this->unreadMap($conversations->getCollection()->all(), 'user', $request->user()->id);

        return view('client.chat.index', compact('conversations', 'unread'));
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorizeParticipant($request, $conversation, 'user');
        $this->chat->markRead($conversation, 'user', $request->user()->id, null);

        $conversation->load(['shop', 'product.images', 'participants']);

        $messages = $conversation->messages()->with([])->paginate(30);

        return view('client.chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeParticipant($request, $conversation, 'user');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('marketplace.chat.max_message_length')],
            'client_message_id' => ['nullable', 'string', 'max:64'],
        ]);

        $message = $this->chat->sendMessage(
            $conversation,
            'user',
            $request->user()->id,
            $data['body'],
            $data['client_message_id'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->messagePayload($message)]);
        }

        return back();
    }

    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation, 'user');

        $after = $request->integer('after', 0);

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->limit(100)
            ->get()
            ->map(fn (Message $m) => $this->messagePayload($m));

        return response()->json([
            'data' => $messages,
            'meta' => ['latest_id' => (int) $conversation->messages()->max('id')],
        ]);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation, 'user');

        $participant = $this->chat->markRead(
            $conversation,
            'user',
            $request->user()->id,
            $request->integer('message_id') ?: null,
        );

        return response()->json(['data' => ['last_read_message_id' => $participant->last_read_message_id]]);
    }

    protected function authorizeParticipant(Request $request, Conversation $conversation, string $type): void
    {
        abort_unless((int) $conversation->client_id === (int) $request->user()->id, 403);
    }

    /**
     * @param  array<int,Conversation>  $conversations
     * @return array<int,int>
     */
    protected function unreadMap(array $conversations, string $type, int $id): array
    {
        $map = [];

        foreach ($conversations as $conversation) {
            $participant = $conversation->participantFor($type, $id);

            if ($participant) {
                $map[$conversation->id] = $this->chat->unreadCount($conversation, $participant);
            }
        }

        return $map;
    }

    protected function messagePayload(Message $message): array
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
