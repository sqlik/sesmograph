<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class HealthController extends Controller
{
    /** Uptime-monitor endpoint: app is up, and how fresh the data is. */
    public function __invoke(): JsonResponse
    {
        $lastAt = Event::query()->max('occurred_at');

        return response()->json([
            'status' => 'ok',
            'time' => now()->toIso8601String(),
            'last_event_at' => $lastAt ? Carbon::parse($lastAt)->toIso8601String() : null,
            'last_event_age_seconds' => $lastAt
                ? (int) now()->diffInSeconds(Carbon::parse($lastAt), true)
                : null,
        ]);
    }
}
