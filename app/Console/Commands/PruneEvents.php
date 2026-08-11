<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Models\Event;
use App\Models\Message;
use App\Models\Topic;
use Illuminate\Console\Command;

class PruneEvents extends Command
{
    protected $signature = 'app:prune-events';

    protected $description = 'Delete raw events and messages past the retention window; daily aggregates stay';

    public function handle(): int
    {
        $default = (int) config('sesmograph.event_retention_days');
        $events = 0;
        $messages = 0;

        foreach (Topic::query()->get() as $topic) {
            $cutoff = now()->subDays($topic->retention_days ?? $default);

            do {
                $deleted = Event::query()
                    ->where('topic_id', $topic->id)
                    ->where('occurred_at', '<', $cutoff)
                    ->limit(500)
                    ->delete();

                $events += $deleted;
            } while ($deleted > 0);

            // A message goes once its whole history is out of the window;
            // last_event_at keeps ones with recent activity alive.
            do {
                $deleted = Message::query()
                    ->where('topic_id', $topic->id)
                    ->where('created_at', '<', $cutoff)
                    ->where(function ($query) use ($cutoff) {
                        $query->whereNull('last_event_at')->orWhere('last_event_at', '<', $cutoff);
                    })
                    ->limit(500)
                    ->delete();

                $messages += $deleted;
            } while ($deleted > 0);
        }

        // API request log: fixed window, not per-topic.
        $logCutoff = now()->subDays((int) config('sesmograph.api_log_retention_days'));
        $logs = 0;

        do {
            $deleted = ApiRequestLog::query()
                ->where('created_at', '<', $logCutoff)
                ->limit(500)
                ->delete();

            $logs += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$events} events, {$messages} messages, and {$logs} API log rows past retention.");

        return self::SUCCESS;
    }
}
