<?php

namespace App\Console\Commands;

use App\Models\MessageContent;
use Illuminate\Console\Command;

class PruneContent extends Command
{
    protected $signature = 'app:prune-content';

    protected $description = 'Delete stored message bodies older than the content retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('sesmograph.content_retention_days'));
        $total = 0;

        do {
            $deleted = MessageContent::query()
                ->where('created_at', '<', $cutoff)
                ->limit(500)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$total} stored bodies older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
