<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookmarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function __construct(
        private readonly BookmarkService $bookmarkService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->bookmarkService->list($request->user()->id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:191'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'format' => ['nullable', 'string', 'max:50'],
        ]);

        $bookmark = $this->bookmarkService->add(
            $request->user()->id,
            $request->input('slug'),
            $request->input('title'),
            $request->input('cover_image'),
            $request->input('format'),
        );

        return response()->json(['data' => $bookmark], 201);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $this->bookmarkService->remove($request->user()->id, $slug);

        return response()->json(['message' => 'Bookmark removed.']);
    }
}
