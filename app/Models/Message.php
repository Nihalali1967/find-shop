<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'sender_type', 'sender_id', 'client_message_id', 'body'])]
class Message extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
