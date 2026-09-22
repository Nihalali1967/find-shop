<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShopRegistrationRequest;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Chat\ChatService;
use App\Services\Shops\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ShopController extends Controller
{
    public function nameAvailability(Request $request, ShopService $shops): JsonResponse
    {
        $key = 'shop-name-check:'.($request->user()?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json(['message' => 'Too many checks.'], 429);
        }

        RateLimiter::hit($key, 60);

        $name = (string) $request->input('name');
        $available = trim($name) !== '' && $shops->isNameAvailable($name);

        return response()->json(['data' => ['available' => $available]]);
    }

    public function register(ShopRegistrationRequest $request, ShopService $shops): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token && ! in_array('shop:onboarding', $token->abilities, true) && ! in_array('*', $token->abilities, true)) {
            return response()->json(['message' => 'Your onboarding session has ended. Verify your mobile again.', 'code' => 'GRANT_INVALID'], 403);
        }

        $shop = $shops->register($request->user(), $request->validated());

        return response()->json(['data' => ['id' => $shop->id, 'name' => $shop->name, 'status' => $shop->status]], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $shop = Shop::withTrashed()->where('owner_id', $request->user()->id)->first();

        if (! $shop) {
            return response()->json(['data' => null, 'meta' => ['has_shop' => false]]);
        }

        return response()->json([
            'data' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'status' => $shop->status,
                'message' => $shop->statusMessage(),
                'can_manage' => $shop->isActive(),
            ],
        ]);
    }

    public function dashboard(Request $request, ChatService $chat): JsonResponse
    {
        $shop = $request->attributes->get('active_shop');

        $conversations = Conversation::where('shop_id', $shop->id)->with('participants')->get();

        $unread = $conversations->filter(function (Conversation $conversation) use ($chat, $shop) {
            $participant = $conversation->participantFor('shop', $shop->id);

            return $participant && $chat->unreadCount($conversation, $participant) > 0;
        })->count();

        return response()->json([
            'data' => [
                'products' => Product::ownedBy($shop->id)->count(),
                'published' => Product::ownedBy($shop->id)->where('status', Product::STATUS_PUBLISHED)->count(),
                'draft' => Product::ownedBy($shop->id)->where('status', Product::STATUS_DRAFT)->count(),
                'conversations' => $conversations->count(),
                'unread_conversations' => $unread,
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('active_shop');

        return response()->json(['data' => $shop->only([
            'id', 'name', 'locality', 'pincode', 'address', 'gst_number', 'secondary_phone', 'email', 'status',
        ])]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('active_shop');

        $data = $request->validate([
            'locality' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'digits:6'],
            'address' => ['required', 'string', 'max:400'],
            'gst_number' => ['nullable', 'string', 'max:15'],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        $shop->fill($data)->save();

        return response()->json(['data' => $shop->only(['id', 'name', 'locality', 'pincode', 'address'])]);
    }
}
