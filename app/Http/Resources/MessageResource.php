<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'body' => $this->body,
            'client_message_id' => $this->client_message_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
