<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Genre;

class GenreController extends Controller
{
    public function index()
    {
        $genres = Genre::query()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $genres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
            ])->all(),
        ]);
    }
}
