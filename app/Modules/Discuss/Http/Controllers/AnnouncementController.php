<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Http\Requests\AnnouncementRequest;
use App\Modules\Discuss\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        return response()->json([
            'announcements' => $this->announcements->listPublished($userId),
            'unread' => $this->announcements->unreadCount($userId),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $this->announcements->unreadCount((string) $request->user()->id),
        ]);
    }

    public function seen(Request $request): JsonResponse
    {
        $this->announcements->markSeen((string) $request->user()->id);

        return response()->json(['unread' => 0]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json(['announcements' => $this->announcements->listAll($request->user())]);
    }

    public function store(AnnouncementRequest $request): JsonResponse
    {
        return response()->json(
            ['announcement' => $this->announcements->create($request->user(), $request->validated())],
            201,
        );
    }

    public function update(AnnouncementRequest $request, string $id): JsonResponse
    {
        return response()->json(
            ['announcement' => $this->announcements->update($request->user(), $id, $request->validated())]
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->announcements->delete($request->user(), $id);

        return response()->json(['message' => 'Announcement deleted.']);
    }
}
