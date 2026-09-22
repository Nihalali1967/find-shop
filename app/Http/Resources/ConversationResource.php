<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'context' => $this->context_snapshot,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'shop' => $this->whenLoaded('shop', fn () => [
                'id' => $this->shop?->id,
                'name' => $this->shop?->name,
                'status' => $this->shop?->status,
            ]),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
            ]),
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
