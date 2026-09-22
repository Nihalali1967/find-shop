<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$type, $id] = $this->recipient($request);

        $notifications = Notification::query()
            ->where('recipient_type', $type)
            ->where('recipient_id', $id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20));

        return NotificationResource::collection($notifications)->response();
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        [$type, $id] = $this->recipient($request);

        abort_unless($notification->recipient_type === $type && (int) $notification->recipient_id === $id, 403);

        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        return response()->json(['data' => new NotificationResource($notification)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        [$type, $id] = $this->recipient($request);

        $updated = Notification::query()
            ->where('recipient_type', $type)
            ->where('recipient_id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => $updated]]);
    }

    /**
     * @return array{0:string,1:int}
     */
    protected function recipient(Request $request): array
    {
        $shop = $request->attributes->get('active_shop');

        if ($shop) {
            return ['shop', $shop->id];
        }

        return ['user', (int) $request->user()->id];
    }
}
