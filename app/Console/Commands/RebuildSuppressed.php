<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\SuppressedAddress;
use Illuminate\Console\Command;

class RebuildSuppressed extends Command
{
    protected $signature = 'app:rebuild-suppressed';

    protected $description = 'Rebuild the suppressed addresses list from the raw events on record';

    public function handle(): int
    {
        SuppressedAddress::query()->delete();

        Event::query()
            ->whereIn('type', ['bounce', 'complaint'])
            ->chunkById(500, function ($events) {
                foreach ($events as $event) {
                    SuppressedAddress::recordFromEvent($event);
                }
            });

        $this->info('Rebuilt '.SuppressedAddress::query()->count().' suppressed addresses.');

        return self::SUCCESS;
    }
}
