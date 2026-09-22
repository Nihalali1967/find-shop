<?php

namespace App\Services\Notifications;

use App\Models\Notification;

class NotificationService
{
    public function push(
        string $recipientType,
        int $recipientId,
        string $eventKey,
        string $type,
        string $title,
        ?string $body = null,
        array $payload = [],
    ): Notification {
        // updateOrCreate keeps the unique event key deduplicated.
        return Notification::updateOrCreate(
            [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'event_key' => $eventKey,
            ],
            [
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
            ],
        );
    }

    public function unreadCount(string $recipientType, int $recipientId): int
    {
        return Notification::query()
            ->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->whereNull('read_at')
            ->count();
    }
}
