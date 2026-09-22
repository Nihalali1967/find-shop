<?php

namespace App\Services\Chat;

use App\Exceptions\MarketplaceException;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class ChatService
{
    public const TYPE_USER = 'user';

    public const TYPE_SHOP = 'shop';

    public function __construct(private readonly NotificationService $notifications) {}

    public function getOrCreate(User $client, Product $product): Conversation
    {
        if ($product->shop && $product->shop->owner_id === $client->id) {
            throw MarketplaceException::invalid('You cannot start a conversation with your own shop.', 'OWN_SHOP');
        }

        $existing = Conversation::query()
            ->where('client_id', $client->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($client, $product) {
                $conversation = Conversation::create([
                    'product_id' => $product->id,
                    'client_id' => $client->id,
                    'shop_id' => $product->shop_id,
                    'context_snapshot' => [
                        'product_name' => $product->name,
                        'product_title' => $product->title,
                        'shop_name' => $product->shop?->name,
                        'price_paise' => $product->price_paise,
                        'offer_price_paise' => $product->offer_price_paise,
                        'cover' => $product->images->first()?->path,
                    ],
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_type' => self::TYPE_USER,
                    'participant_id' => $client->id,
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_type' => self::TYPE_SHOP,
                    'participant_id' => $product->shop_id,
                ]);

                return $conversation;
            });
        } catch (QueryException $e) {
            // Double-click race: another thread created it first.
            return Conversation::query()
                ->where('client_id', $client->id)
                ->where('product_id', $product->id)
                ->firstOrFail();
        }
    }

    /**
     * Authorize a participant and return their participant row.
     */
    public function participant(Conversation $conversation, string $type, int $id): ConversationParticipant
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('participant_type', $type)
            ->where('participant_id', $id)
            ->first();

        if (! $participant) {
            throw new MarketplaceException('FORBIDDEN', 'You do not have access to this conversation.', 403);
        }

        return $participant;
    }

    public function sendMessage(
        Conversation $conversation,
        string $senderType,
        int $senderId,
        string $body,
        ?string $clientMessageId = null,
    ): Message {
        $this->participant($conversation, $senderType, $senderId);

        $body = trim($body);

        if ($body === '') {
            throw MarketplaceException::invalid('Write a message before sending.', 'EMPTY_MESSAGE');
        }

        if (mb_strlen($body) > (int) config('marketplace.chat.max_message_length')) {
            throw MarketplaceException::invalid('That message is too long.', 'MESSAGE_TOO_LONG');
        }

        // A shop may only send while active; clients are never restricted.
        if ($senderType === self::TYPE_SHOP) {
            $shop = Shop::find($senderId);
            if (! $shop || ! $shop->isActive()) {
                throw MarketplaceException::shopInactive(
                    'Your shop account is not active, so you cannot send messages.',
                    $shop && $shop->status === Shop::STATUS_SUSPENDED ? 'SHOP_SUSPENDED' : 'SHOP_INACTIVE'
                );
            }
        }

        $rateKey = "chat:{$conversation->id}:{$senderType}:{$senderId}";

        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            throw MarketplaceException::otpThrottled(RateLimiter::availableIn($rateKey), 'You are sending messages too quickly.');
        }

        RateLimiter::hit($rateKey, 60);

        if ($clientMessageId) {
            $existing = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_type', $senderType)
                ->where('client_message_id', $clientMessageId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $message = DB::transaction(function () use ($conversation, $senderType, $senderId, $body, $clientMessageId) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'client_message_id' => $clientMessageId,
                'body' => $body,
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        $this->notifyRecipient($conversation, $senderType, $message);

        return $message;
    }

    public function markRead(Conversation $conversation, string $type, int $id, ?int $messageId): ConversationParticipant
    {
        $participant = $this->participant($conversation, $type, $id);

        $latestId = (int) $conversation->messages()->max('id');
        $target = $messageId ? min($messageId, $latestId) : $latestId;

        if ($messageId) {
            $belongs = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('id', $messageId)
                ->exists();

            if (! $belongs) {
                throw MarketplaceException::invalid('That message is not part of this conversation.', 'CURSOR_INVALID');
            }
        }

        // Never move the cursor backwards.
        if ((int) $participant->last_read_message_id >= $target) {
            return $participant;
        }

        $participant->forceFill([
            'last_read_message_id' => $target,
            'last_read_at' => now(),
        ])->save();

        return $participant;
    }

    public function unreadCount(Conversation $conversation, ConversationParticipant $participant): int
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_type', '!=', $participant->participant_type)
            ->where('id', '>', (int) $participant->last_read_message_id)
            ->count();
    }

    protected function notifyRecipient(Conversation $conversation, string $senderType, Message $message): void
    {
        if ($senderType === self::TYPE_USER) {
            $this->notifications->push(
                self::TYPE_SHOP,
                $conversation->shop_id,
                "message:{$message->id}",
                'new_message',
                'New enquiry on '.($conversation->context_snapshot['product_name'] ?? 'a product'),
                mb_substr($message->body, 0, 140),
                ['conversation_id' => $conversation->id],
            );

            return;
        }

        $this->notifications->push(
            self::TYPE_USER,
            $conversation->client_id,
            "message:{$message->id}",
            'new_message',
            'New reply from '.($conversation->context_snapshot['shop_name'] ?? 'a shop'),
            mb_substr($message->body, 0, 140),
            ['conversation_id' => $conversation->id],
        );
    }
}
