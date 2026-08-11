<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventSearchController extends Controller
{
    public function __invoke(Request $request, ApiQueries $queries): JsonResponse
    {
        // Array-shaped values (?q[]=x) must fail as 422, not as TypeErrors.
        $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'topic' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ]);

        return response()->json($queries->searchEvents(
            $request->only(['q', 'topic', 'type', 'from', 'to', 'page', 'per_page']),
        ));
    }
}
