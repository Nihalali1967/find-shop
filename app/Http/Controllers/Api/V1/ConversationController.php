<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()->visible()->with('images')->findOrFail($data['product_id']);

        $conversation = $this->chat->getOrCreate($request->user(), $product);

        return response()->json(['data' => new ConversationResource($conversation->load('shop'))], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('active_shop');

        $query = Conversation::query()
            ->when($shop, fn (Builder $q) => $q->where('shop_id', $shop->id), fn (Builder $q) => $q->where('client_id', $request->user()->id))
            ->with(['shop', 'client', 'participants'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $conversations = $query->paginate((int) $request->input('per_page', 15));

        $conversations->getCollection()->transform(function (Conversation $conversation) use ($request, $shop) {
            $type = $shop ? 'shop' : 'user';
            $id = $shop ? $shop->id : $request->user()->id;
            $participant = $conversation->participantFor($type, $id);

            $conversation->unread_count = $participant ? $this->chat->unreadCount($conversation, $participant) : 0;

            return $conversation;
        });

        return ConversationResource::collection($conversations)->response();
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        [$type, $id] = $this->actor($conversation, $request->user());
        $this->chat->participant($conversation, $type, $id);

        $messages = $conversation->messages()
            ->when($request->integer('after'), fn (Builder $q, $after) => $q->where('id', '>', $after))
            ->when($request->integer('before'), fn (Builder $q, $before) => $q->where('id', '<', $before))
            ->orderBy('id')
            ->paginate((int) $request->input('per_page', 30));

        return MessageResource::collection($messages)->response();
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        [$type, $id] = $this->actor($conversation, $request->user());

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('marketplace.chat.max_message_length')],
            'client_message_id' => ['nullable', 'string', 'max:64'],
        ]);

        $message = $this->chat->sendMessage($conversation, $type, $id, $data['body'], $data['client_message_id'] ?? null);

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        [$type, $id] = $this->actor($conversation, $request->user());

        $data = $request->validate([
            'message_id' => ['nullable', 'integer'],
        ]);

        $participant = $this->chat->markRead($conversation, $type, $id, $data['message_id'] ?? null);

        return response()->json(['data' => ['last_read_message_id' => $participant->last_read_message_id]]);
    }

    /**
     * @return array{0:string,1:int}
     */
    protected function actor(Conversation $conversation, User $user): array
    {
        if ((int) $conversation->client_id === (int) $user->id) {
            return ['user', (int) $user->id];
        }

        $shop = $conversation->shop;

        if ($shop && (int) $shop->owner_id === (int) $user->id) {
            return ['shop', (int) $shop->id];
        }

        abort(403, 'You do not have access to this conversation.');
    }
}
