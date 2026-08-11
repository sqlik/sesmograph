<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageContent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageContentController extends Controller
{
    public function store(Request $request, string $sesMessageId): JsonResponse
    {
        // The id becomes a string(255) column, so reject longer values here
        // rather than letting the insert throw a 500 on a strict database.
        if (strlen($sesMessageId) > 255) {
            return response()->json(['message' => 'SES message ID is too long.'], 422);
        }

        $max = (int) config('sesmograph.content_max_bytes');

        // Byte-bounded, not character-bounded: the column and html_bytes are
        // measured in bytes, so a multi-byte body must be checked the same way.
        $withinBytes = fn (?string $value) => $value === null || strlen($value) <= $max;

        $data = $request->validate([
            'html' => ['nullable', 'string', fn ($a, $v, $fail) => $withinBytes($v) ?: $fail("The html may not be larger than {$max} bytes.")],
            'text' => ['nullable', 'string', fn ($a, $v, $fail) => $withinBytes($v) ?: $fail("The text may not be larger than {$max} bytes.")],
        ]);

        if (($data['html'] ?? null) === null && ($data['text'] ?? null) === null) {
            return response()->json(['message' => 'Provide html, text, or both.'], 422);
        }

        try {
            $content = $this->fill($sesMessageId, $data);
        } catch (UniqueConstraintViolationException) {
            // Two first-content posts raced; the loser retries as an update.
            $content = $this->fill($sesMessageId, $data);
        }

        return response()->json(['stored' => true], $content->wasRecentlyCreated ? 201 : 200);
    }

    /** Write only the posted fields, so posting html later cannot null text. */
    private function fill(string $sesMessageId, array $data): MessageContent
    {
        $content = MessageContent::query()->firstOrNew(['ses_message_id' => $sesMessageId]);

        if (array_key_exists('html', $data)) {
            $content->html = $data['html'];
            $content->html_bytes = strlen($data['html'] ?? '');
        }

        if (array_key_exists('text', $data)) {
            $content->text = $data['text'];
        }

        // Events may have arrived first - link immediately if so.
        $content->message_id ??= Message::query()
            ->where('ses_message_id', $sesMessageId)
            ->orderByDesc('id')
            ->value('id');

        $content->save();

        return $content;
    }
}
