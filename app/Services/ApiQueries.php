<?php

namespace App\Services;

use App\Models\DailyAggregate;
use App\Models\Event;
use App\Models\Message;
use App\Models\SuppressedAddress;
use App\Models\Topic;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Read-model queries shared by the REST API and the MCP tools, so both
 * surfaces accept the same filters and return identical shapes.
 */
class ApiQueries
{
    /**
     * @param  array{q?: ?string, topic?: ?string, type?: ?string, from?: ?string, to?: ?string, page?: mixed, per_page?: mixed}  $filters
     * @return array{data: list<array<string, mixed>>, page: int, per_page: int, total: int}
     */
    public function searchEvents(array $filters): array
    {
        $type = $filters['type'] ?? null;

        if ($type !== null && ! in_array($type, Event::TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => 'Unknown event type. Use one of: '.implode(', ', Event::TYPES).'.',
            ]);
        }

        $topicId = $this->topicId($filters['topic'] ?? null);

        $events = Event::query()
            ->with(['topic:id,name', 'message:id,ses_message_id,subject,from_email,recipients,status'])
            ->when($topicId !== null, fn (Builder $q) => $q->where('topic_id', $topicId))
            ->when($type !== null, fn (Builder $q) => $q->where('type', $type))
            ->when(($filters['from'] ?? null) !== null, fn (Builder $q) => $q->where('occurred_at', '>=', $this->date($filters['from'], 'from')))
            ->when(($filters['to'] ?? null) !== null, fn (Builder $q) => $q->where('occurred_at', '<', $this->date($filters['to'], 'to')->addDay()))
            ->when(($filters['q'] ?? null) !== null, function (Builder $q) use ($filters) {
                $term = trim((string) $filters['q']);

                $q->whereHas('message', function (Builder $m) use ($term) {
                    $m->where('subject', 'like', "%{$term}%")
                        ->orWhere('from_email', 'like', "%{$term}%")
                        ->orWhere('recipients', 'like', "%{$term}%")
                        ->orWhere('ses_message_id', $term);
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(
                min(max((int) ($filters['per_page'] ?? 25), 1), 100),
                page: max((int) ($filters['page'] ?? 1), 1),
            );

        return [
            'data' => $events->map(fn (Event $event) => [
                'type' => $event->type,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'topic' => $event->topic->name,
                'message' => [
                    'ses_message_id' => $event->message->ses_message_id,
                    'subject' => $event->message->subject,
                    'from' => $event->message->from_email,
                    'recipients' => $event->message->recipients,
                    'status' => $event->message->status,
                ],
                'details' => $event->details(),
            ])->all(),
            'page' => $events->currentPage(),
            'per_page' => $events->perPage(),
            'total' => $events->total(),
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function messageTimeline(string $sesMessageId): ?array
    {
        // A send fanned out to several topics has one message row per
        // topic; pick the most recently active one deterministically.
        $message = Message::query()
            ->with('topic:id,name')
            ->where('ses_message_id', trim($sesMessageId))
            ->orderByDesc('last_event_at')
            ->orderByDesc('id')
            ->first();

        if ($message === null) {
            return null;
        }

        return [
            'ses_message_id' => $message->ses_message_id,
            'topic' => $message->topic->name,
            'subject' => $message->subject,
            'from' => $message->from_email,
            'recipients' => $message->recipients,
            'status' => $message->status,
            'last_event_at' => $message->last_event_at?->toIso8601String(),
            'events' => $message->events()
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get()
                ->map(fn (Event $event) => [
                    'type' => $event->type,
                    'label' => $event->label(),
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                    'details' => $event->details(),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(?string $topic, ?string $from, ?string $to): array
    {
        $topicId = $this->topicId($topic);
        $start = $from !== null ? $this->date($from, 'from') : today()->subDays(29);
        $end = $to !== null ? $this->date($to, 'to') : today();

        if ($start->gt($end)) {
            throw ValidationException::withMessages(['from' => 'The from date must not be after the to date.']);
        }

        $sums = implode(', ', array_map(
            fn (string $type) => "sum({$type}_count) as {$type}",
            Event::TYPES,
        ));

        $rows = DailyAggregate::query()
            ->when($topicId !== null, fn (Builder $q) => $q->where('topic_id', $topicId))
            // String bounds break on drivers that store date columns with
            // a time part; half-open Carbon bounds compare correctly.
            ->where('date', '>=', $start)
            ->where('date', '<', $end->copy()->addDay())
            ->selectRaw("date, {$sums}")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $days = $rows->map(fn ($row) => ['date' => Carbon::parse($row->date)->toDateString()]
            + collect(Event::TYPES)->mapWithKeys(fn (string $type) => [$type => (int) $row->{$type}])->all(),
        )->all();

        $totals = collect(Event::TYPES)
            ->mapWithKeys(fn (string $type) => [$type => array_sum(array_column($days, $type))])
            ->all();

        return [
            'topic' => $topicId !== null ? Topic::query()->find($topicId)?->name : null,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'totals' => $totals,
            'bounce_rate' => Stats::rate($totals['bounce'], $totals['send']),
            'complaint_rate' => Stats::rate($totals['complaint'], $totals['send']),
            'days' => $days,
        ];
    }

    /**
     * @return array{address: string, suppressed: bool, entries: list<array<string, mixed>>}
     */
    public function checkAddress(string $address): array
    {
        $address = Str::lower(trim($address));

        if ($address === '') {
            throw ValidationException::withMessages(['address' => 'An email address is required.']);
        }

        $entries = SuppressedAddress::query()
            ->with('topic:id,name')
            ->where('address', $address)
            ->orderByDesc('last_event_at')
            ->get();

        return [
            'address' => $address,
            'suppressed' => $entries->isNotEmpty(),
            'entries' => $entries->map(fn (SuppressedAddress $entry) => $this->suppressedJson($entry))->all(),
        ];
    }

    /**
     * @param  array{topic?: ?string, reason?: ?string, page?: mixed, per_page?: mixed}  $filters
     * @return array{data: list<array<string, mixed>>, page: int, per_page: int, total: int}
     */
    public function suppressedList(array $filters): array
    {
        $reason = $filters['reason'] ?? null;

        if ($reason !== null && ! in_array($reason, SuppressedAddress::REASONS, true)) {
            throw ValidationException::withMessages([
                'reason' => 'Unknown reason. Use one of: '.implode(', ', SuppressedAddress::REASONS).'.',
            ]);
        }

        $topicId = $this->topicId($filters['topic'] ?? null);

        $entries = SuppressedAddress::query()
            ->with('topic:id,name')
            ->when($topicId !== null, fn (Builder $q) => $q->where('topic_id', $topicId))
            ->when($reason !== null, fn (Builder $q) => $q->where('reason', $reason))
            ->orderByDesc('last_event_at')
            ->orderByDesc('id')
            ->paginate(
                min(max((int) ($filters['per_page'] ?? 50), 1), 200),
                page: max((int) ($filters['page'] ?? 1), 1),
            );

        return [
            'data' => $entries->map(fn (SuppressedAddress $entry) => $this->suppressedJson($entry))->all(),
            'page' => $entries->currentPage(),
            'per_page' => $entries->perPage(),
            'total' => $entries->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function suppressedJson(SuppressedAddress $entry): array
    {
        return [
            'address' => $entry->address,
            'topic' => $entry->topic->name,
            'reason' => $entry->reason,
            'hits' => $entry->hits,
            'last_event_at' => $entry->last_event_at->toIso8601String(),
            'diagnostic' => $entry->last_diagnostic,
        ];
    }

    /** Resolve a topic given by ID or name; null passes through. */
    private function topicId(?string $topic): ?int
    {
        if ($topic === null || trim($topic) === '') {
            return null;
        }

        $id = is_numeric($topic)
            ? Topic::query()->whereKey((int) $topic)->value('id')
            : Topic::query()->where('name', trim($topic))->value('id');

        if ($id === null) {
            throw ValidationException::withMessages(['topic' => 'Unknown topic. Use a topic name or ID.']);
        }

        return $id;
    }

    private function date(string $value, string $field): Carbon
    {
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Use a date in YYYY-MM-DD format.']);
        }
    }
}
