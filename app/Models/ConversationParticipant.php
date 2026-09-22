<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id', 'participant_type', 'participant_id',
    'last_read_message_id', 'last_read_at',
])]
class ConversationParticipant extends Model
{
    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
