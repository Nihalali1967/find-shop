<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return (int) $conversation->client_id === (int) $user->id
            || (int) $conversation->shop?->owner_id === (int) $user->id;
    }

    public function send(User $user, Conversation $conversation): bool
    {
        if ((int) $conversation->client_id === (int) $user->id) {
            return true;
        }

        return (int) $conversation->shop?->owner_id === (int) $user->id
            && (bool) $conversation->shop?->isActive();
    }
}
