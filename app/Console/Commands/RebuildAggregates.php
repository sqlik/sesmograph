<?php

namespace App\Console\Commands;

use App\Models\DailyAggregate;
use App\Models\Event;
use Illuminate\Console\Command;

class RebuildAggregates extends Command
{
    protected $signature = 'app:rebuild-aggregates';

    protected $description = 'Recount every daily aggregate from the raw events on record';

    public function handle(): int
    {
        $days = Event::query()
            ->selectRaw('topic_id, date(occurred_at) as day')
            ->groupBy('topic_id', 'day')
            ->orderBy('day')
            ->get();

        foreach ($days as $row) {
            DailyAggregate::recount($row->topic_id, $row->day);
        }

        $this->info("Rebuilt {$days->count()} topic-day aggregates.");

        return self::SUCCESS;
    }
}
