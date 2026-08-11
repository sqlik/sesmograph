<?php

namespace App\Jobs;

use App\Models\DailyAggregate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshDailyAggregate implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $topicId,
        public string $date,
    ) {}

    public function handle(): void
    {
        DailyAggregate::recount($this->topicId, $this->date);
    }
}
