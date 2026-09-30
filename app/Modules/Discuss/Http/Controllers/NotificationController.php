<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Http\Resources\NotificationResource;
use App\Modules\Discuss\Models\Notification;
use App\Modules\Discuss\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class NotificationController
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): ResourceCollection
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return NotificationResource::collection($notifications);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllRead($request->user()->id);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
