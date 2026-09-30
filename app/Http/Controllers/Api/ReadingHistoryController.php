<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReadingHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingHistoryController extends Controller
{
    public function __construct(
        private readonly ReadingHistoryService $readingHistoryService,
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        return response()->json([
            'read_chapters' => $this->readingHistoryService->readChapters($request->user()->id, $slug),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->readingHistoryService->recent($request->user()->id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:191'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'chapter_index' => ['required', 'integer', 'min:0'],
            'chapter_title' => ['nullable', 'string', 'max:191'],
            'chapter_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $entry = $this->readingHistoryService->markRead(
            $request->user()->id,
            $request->input('slug'),
            $request->input('title'),
            $request->input('cover_image'),
            $request->input('chapter_index'),
            $request->input('chapter_title'),
            $request->input('chapter_url'),
        );

        return response()->json(['data' => $entry], 201);
    }
}
