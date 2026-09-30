<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Http\Requests\CpEventRequest;
use App\Modules\Discuss\Http\Requests\UpdateCpSettingsRequest;
use App\Modules\Discuss\Services\CpEventService;
use App\Modules\Discuss\Services\CpSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CpAdminController
{
    public function __construct(
        private readonly CpSettingsService $settings,
        private readonly CpEventService $events,
    ) {}

    public function showSettings(Request $request): JsonResponse
    {
        return response()->json($this->settings->show($request->user()));
    }

    public function updateSettings(UpdateCpSettingsRequest $request): JsonResponse
    {
        return response()->json($this->settings->update($request->user(), $request->validated()));
    }

    public function listEvents(Request $request): JsonResponse
    {
        return response()->json(['events' => $this->events->list($request->user())]);
    }

    public function storeEvent(CpEventRequest $request): JsonResponse
    {
        return response()->json(['event' => $this->events->create($request->user(), $request->validated())], 201);
    }

    public function updateEvent(CpEventRequest $request, string $eventId): JsonResponse
    {
        return response()->json(['event' => $this->events->update($request->user(), $eventId, $request->validated())]);
    }

    public function destroyEvent(Request $request, string $eventId): JsonResponse
    {
        $this->events->delete($request->user(), $eventId);

        return response()->json(['message' => 'CP event deleted.']);
    }

    /** Event yang sedang berlangsung — untuk semua user login (banner "Double CP"). */
    public function activeEvents(): JsonResponse
    {
        return response()->json(['events' => $this->events->active()]);
    }
}
