<?php

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = Notification::query()
            ->where('recipient_type', 'user')
            ->where('recipient_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('client.notifications.index', compact('notifications'));
    }

    public function read(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless(
            $notification->recipient_type === 'user' && (int) $notification->recipient_id === (int) $request->user()->id,
            403
        );

        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        $url = $this->safeLink($request, $notification);

        return $url ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        Notification::query()
            ->where('recipient_type', 'user')
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }

    protected function safeLink(Request $request, Notification $notification): ?string
    {
        $conversationId = $notification->payload['conversation_id'] ?? null;

        if (! $conversationId) {
            return null;
        }

        $exists = \App\Models\Conversation::query()
            ->where('id', $conversationId)
            ->where('client_id', $request->user()->id)
            ->exists();

        return $exists ? route('client.chat.show', $conversationId) : null;
    }
}
