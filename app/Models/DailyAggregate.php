<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Recount one topic's day from raw events and store the result.
     * Idempotent by design: safe to run again after retries or replays.
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
