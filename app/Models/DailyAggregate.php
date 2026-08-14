<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

class DailyAggregate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Count one newly inserted event into its topic-day row. The caller
     * must guarantee the event is new (the idempotent event insert does),
     * so a single guarded increment cannot double-count - O(1) per event
     * instead of recounting the whole day.
     */
    public static function record(int $topicId, string $date, string $type): void
    {
        // Same serialization rule as recount(): the lookup must match the
        // stored value exactly, so normalize through Carbon::startOfDay().
        $day = Carbon::parse($date)->startOfDay();

        $updated = self::query()
            ->where('topic_id', $topicId)
            ->where('date', $day)
            ->increment("{$type}_count");

        if ($updated > 0) {
            return;
        }

        // First event of this topic-day: recount creates the row and
        // already includes the event being recorded. If a concurrent
        // request wins the insert race, recount again - it stores
        // absolute counts, so running twice can never double-count.
        try {
            self::recount($topicId, $day);
        } catch (UniqueConstraintViolationException) {
            self::recount($topicId, $day);
        }
    }

    /**
     * Recount one topic's day from raw events and store the result.
     * Idempotent by design: safe to run again after retries or replays.
     * The repair path (app:rebuild-aggregates, seeder) - live ingestion
     * goes through record() above.
     */
    public static function recount(int $topicId, Carbon|string $date): self
    {
        $date = Carbon::parse($date)->startOfDay();

        $counts = Event::query()
            ->where('topic_id', $topicId)
            ->whereBetween('occurred_at', [$date, $date->copy()->endOfDay()])
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $columns = [];

        foreach (Event::TYPES as $type) {
            $columns["{$type}_count"] = (int) ($counts[$type] ?? 0);
        }

        // Pass the Carbon instance, not a string: the lookup must serialize
        // exactly like the stored value or every recount tries an insert.
        return self::query()->updateOrCreate(
            ['topic_id' => $topicId, 'date' => $date],
            $columns,
        );
    }

    /** Bounces as a share of sends, in percent - AWS warns at 5%. */
    public function bounceRate(): ?float
    {
        return $this->send_count > 0 ? $this->bounce_count / $this->send_count * 100 : null;
    }

    /** Complaints as a share of sends, in percent - AWS warns at 0.1%. */
    public function complaintRate(): ?float
    {
        return $this->send_count > 0 ? $this->complaint_count / $this->send_count * 100 : null;
    }
}
