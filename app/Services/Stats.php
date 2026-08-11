<?php

namespace App\Services;

use App\Models\DailyAggregate;
use App\Models\Event;
use Illuminate\Support\Carbon;

/**
 * Dashboard reads. Everything here queries daily_aggregates, never the
 * raw events table - except sub-day counters,
 * which need event timestamps and stay on an indexed range scan.
 */
class Stats
{
    /**
     * Per-day aggregate sums for the trailing window, zero-filled so
     * charts get one point per calendar day.
     *
     * @return array{categories: list<string>, days: list<array<string, int|string>>}
     */
    public function daily(int|array|null $topicId, int $days = 30): array
    {
        $start = today()->subDays($days - 1);

        $sums = implode(', ', array_map(
            fn (string $type) => "sum({$type}_count) as {$type}",
            Event::TYPES,
        ));

        $rows = DailyAggregate::query()
            ->when($topicId !== null, fn ($query) => $query->whereIn('topic_id', (array) $topicId))
            ->where('date', '>=', $start->toDateString())
            ->selectRaw("date, {$sums}")
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $categories = [];
        $daily = [];

        for ($day = $start->copy(); $day->lte(today()); $day->addDay()) {
            $key = $day->toDateString();
            $categories[] = $day->format('M j');

            $daily[] = ['date' => $key] + collect(Event::TYPES)
                ->mapWithKeys(fn (string $type) => [$type => (int) ($rows[$key]?->{$type} ?? 0)])
                ->all();
        }

        return ['categories' => $categories, 'days' => $daily];
    }

    /**
     * Aggregate totals per event type over the trailing window.
     *
     * @return array<string, int>
     */
    public function totals(int|array|null $topicId, int $days = 30): array
    {
        $sums = implode(', ', array_map(
            fn (string $type) => "sum({$type}_count) as {$type}",
            Event::TYPES,
        ));

        $row = DailyAggregate::query()
            ->when($topicId !== null, fn ($query) => $query->whereIn('topic_id', (array) $topicId))
            ->where('date', '>=', today()->subDays($days - 1)->toDateString())
            ->selectRaw($sums)
            ->first();

        return collect(Event::TYPES)
            ->mapWithKeys(fn (string $type) => [$type => (int) ($row?->{$type} ?? 0)])
            ->all();
    }

    /** Send events in the trailing sub-day window, from raw events. */
    public function sendsSince(int|array|null $topicId, Carbon $since): int
    {
        return Event::query()
            ->when($topicId !== null, fn ($query) => $query->whereIn('topic_id', (array) $topicId))
            ->where('type', 'send')
            ->where('occurred_at', '>=', $since)
            ->count();
    }

    /** Percentage of $part in $whole, null when there is no traffic. */
    public static function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : null;
    }
}
