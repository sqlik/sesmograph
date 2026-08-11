<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiQueries;
use Illuminate\Http\JsonResponse;

class MessageTimelineController extends Controller
{
    public function __invoke(string $sesMessageId, ApiQueries $queries): JsonResponse
    {
        $timeline = $queries->messageTimeline($sesMessageId);

        if ($timeline === null) {
            return response()->json(['message' => 'No message with this SES message ID.'], 404);
        }

        return response()->json($timeline);
    }
}
