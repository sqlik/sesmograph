<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuppressedController extends Controller
{
    public function __invoke(Request $request, ApiQueries $queries): JsonResponse
    {
        // Array-shaped values (?address[]=x) must fail as 422, not as TypeErrors.
        $request->validate([
            'address' => ['nullable', 'string'],
            'topic' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ]);

        // ?address= answers "is it safe to send to this address" for
        // callers like n8n; without it the endpoint lists the ledger.
        if ($request->filled('address')) {
            return response()->json($queries->checkAddress($request->query('address')));
        }

        return response()->json($queries->suppressedList(
            $request->only(['topic', 'reason', 'page', 'per_page']),
        ));
    }
}
