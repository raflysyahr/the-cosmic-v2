<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Discuss\Http\Requests\ResolveReportRequest;
use App\Modules\Discuss\Http\Requests\SubmitReportRequest;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function store(SubmitReportRequest $request, string $slug, string $messageId): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $report = $this->reports->submit(
            $room->id,
            $messageId,
            $request->user()->id,
            $request->validated('reason'),
            $request->validated('note'),
        );

        return response()->json(['report' => $report], 201);
    }

    public function index(Request $request, string $slug): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        return response()->json([
            'reports' => $this->reports->listForRoom(
                $room->id,
                $request->user()->id,
                (string) $request->query('status', 'pending'),
            ),
        ]);
    }

    public function resolve(ResolveReportRequest $request, string $slug, string $reportId): JsonResponse
    {
        $room = Room::where('slug', $slug)->firstOrFail();

        $report = $this->reports->resolve(
            $room->id,
            $reportId,
            $request->user()->id,
            $request->validated('outcome'),
            $request->validated('penalty'),
            (bool) $request->validated('delete_message', false),
        );

        return response()->json(['report' => $report]);
    }
}
