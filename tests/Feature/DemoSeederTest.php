<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Message;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_never_seeds_events_in_the_future(): void
    {
        // Midday: the seeder's 07:00-21:00 send window for "today" spans
        // both sides of now, so future slots exist and must be skipped.
        Carbon::setTestNow(Carbon::now()->setTime(12, 0));

        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(0, Message::count());
        $this->assertGreaterThan(0, Event::count());
        $this->assertSame(0, Event::where('occurred_at', '>', now())->count());
        $this->assertSame(0, Message::where('last_event_at', '>', now())->count());
    }
}
