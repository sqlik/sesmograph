<?php

namespace App\Services;

use App\Jobs\TriggerImmediateAlerts;
use App\Models\DailyAggregate;
use App\Models\Event;
use App\Models\Message;
use App\Models\MessageContent;
use App\Models\SuppressedAddress;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SesEventProcessor
{
    /**
     * Store one SES event: upsert its message, insert the event
     * (idempotently), and update the message status.
     */
    public function process(Topic $topic, array $event): void
    {
        $type = $this->eventType($event);

        if ($type === null || ! in_array($type, Event::TYPES, true)) {
            return;
        }

        $mail = $event['mail'] ?? [];
        $sesMessageId = $mail['messageId'] ?? null;

        if ($sesMessageId === null) {
            return;
        }

        $occurredAt = $this->occurredAt($event, $type);

        /** @var Message $message */
        $message = $topic->messages()->firstOrCreate(
            ['ses_message_id' => $sesMessageId],
            [
                'subject' => Str::limit(Arr::get($mail, 'commonHeaders.subject') ?? '', 990) ?: null,
                'from_email' => Arr::get($mail, 'commonHeaders.from.0') ?? Arr::get($mail, 'source'),
                'recipients' => Arr::get($mail, 'commonHeaders.to') ?? Arr::get($mail, 'destination'),
            ],
        );

        // Content ingested before the first event waits unlinked; adopt it.
        if ($message->wasRecentlyCreated) {
            MessageContent::query()
                ->where('ses_message_id', $sesMessageId)
                ->whereNull('message_id')
                ->update(['message_id' => $message->id]);
        }

        $stored = $message->events()->firstOrCreate(
            ['type' => $type, 'occurred_at' => $occurredAt],
            ['topic_id' => $topic->id, 'payload' => $event],
        );

        $message->applyEvent($type, $occurredAt);

        // One O(1) increment per new event; the idempotent insert above
        // already filtered SNS replays, so this cannot double-count.
        if ($stored->wasRecentlyCreated) {
            DailyAggregate::record($topic->id, $occurredAt->toDateString(), $type);

            if (in_array($type, ['bounce', 'complaint'], true)) {
                SuppressedAddress::recordFromEvent($stored);
                TriggerImmediateAlerts::dispatchAfterResponse($stored->id);
            }
        }
    }

    private function eventType(array $event): ?string
    {
        // Event publishing sends "eventType"; legacy SNS notifications
        // send "notificationType". Both use PascalCase values.
        $raw = $event['eventType'] ?? $event['notificationType'] ?? null;

        return $raw === null ? null : Str::snake($raw);
    }

    private function occurredAt(array $event, string $type): CarbonImmutable
    {
        $detailKey = Str::camel($type);

        $timestamp = Arr::get($event, "{$detailKey}.timestamp")
            ?? Arr::get($event, 'mail.timestamp');

        // SES timestamps arrive in UTC; align them with the app timezone so
        // stored events, sub-day counters and daily buckets stay consistent.
        return $timestamp !== null
            ? CarbonImmutable::parse($timestamp)->setTimezone(config('app.timezone'))
            : CarbonImmutable::now();
    }
}
