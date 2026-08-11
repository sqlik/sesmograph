<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SuppressedAddress extends Model
{
    protected $guarded = [];

    public const REASONS = ['bounce', 'complaint'];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Add the event's recipients to the topic's suppression list.
     * Only hard (Permanent) bounces and complaints qualify; the caller
     * is responsible for passing each stored event exactly once.
     */
    public static function recordFromEvent(Event $event): void
    {
        foreach (self::suppressionsIn($event) as $suppression) {
            $entry = self::query()->firstOrCreate(
                ['topic_id' => $event->topic_id, 'address' => $suppression['address']],
                [
                    'reason' => $suppression['reason'],
                    'last_diagnostic' => $suppression['diagnostic'],
                    'last_event_at' => $event->occurred_at,
                ],
            );

            if ($entry->wasRecentlyCreated) {
                continue;
            }

            $entry->hits++;

            if ($event->occurred_at->gte($entry->last_event_at)) {
                $entry->reason = $suppression['reason'];
                $entry->last_diagnostic = $suppression['diagnostic'] ?? $entry->last_diagnostic;
                $entry->last_event_at = $event->occurred_at;
            }

            $entry->save();
        }
    }

    /**
     * Addresses the event suppresses, with reason and diagnostic.
     *
     * @return list<array{address: string, reason: string, diagnostic: ?string}>
     */
    private static function suppressionsIn(Event $event): array
    {
        $recipients = match ($event->type) {
            'bounce' => Arr::get($event->payload, 'bounce.bounceType') === 'Permanent'
                ? Arr::get($event->payload, 'bounce.bouncedRecipients', [])
                : [],
            'complaint' => Arr::get($event->payload, 'complaint.complainedRecipients', []),
            default => [],
        };

        $suppressions = [];

        foreach ($recipients as $recipient) {
            $address = Str::lower(trim((string) ($recipient['emailAddress'] ?? '')));

            if ($address === '') {
                continue;
            }

            $suppressions[] = [
                'address' => $address,
                'reason' => $event->type,
                'diagnostic' => Str::limit($recipient['diagnosticCode'] ?? '', 490) ?: null,
            ];
        }

        return $suppressions;
    }
}
