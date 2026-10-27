<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleChapterReactionRequest;
use App\Services\ChapterReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterReactionController extends Controller
{
    public function __construct(
        private readonly ChapterReactionService $reactions,
    ) {}

    /** Publik: tamu juga bisa melihat jumlah reaksi (my_reaction = null untuk tamu). */
    public function show(Request $request, string $slug, int $index): JsonResponse
    {
        $this->reactions->assertChapterExists($slug, $index);

        return response()->json($this->reactions->summary($slug, $index, $request->user()?->id));
    }

    public function toggle(ToggleChapterReactionRequest $request, string $slug, int $index): JsonResponse
    {
        return response()->json(
            $this->reactions->toggle($request->user()->id, $slug, $index, (string) $request->input('reaction')),
        );
    }
}
