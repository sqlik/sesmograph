<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __invoke(Request $request, ApiQueries $queries): JsonResponse
    {
        // Array-shaped values (?topic[]=x) must fail as 422, not as TypeErrors.
        $request->validate([
            'topic' => ['nullable', 'string'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
        ]);

        return response()->json($queries->stats(
            $request->query('topic'),
            $request->query('from'),
            $request->query('to'),
        ));
    }
}
