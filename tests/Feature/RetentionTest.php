<?php

namespace Tests\Feature;

use App\Models\DailyAggregate;
use App\Models\Event;
use App\Models\Message;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private function messageWithEvent(Topic $topic, \DateTimeInterface $at): Message
    {
        $message = Message::factory()->for($topic)->create([
            'created_at' => $at,
            'last_event_at' => $at,
        ]);

        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'send',
            'occurred_at' => $at,
            'payload' => [],
        ]);

        return $message;
    }

    public function test_prune_removes_events_and_messages_past_the_window(): void
    {
        $topic = Topic::factory()->create();
        $old = $this->messageWithEvent($topic, now()->subDays(100));
        $fresh = $this->messageWithEvent($topic, now()->subDays(5));

        $this->artisan('app:prune-events')->assertSuccessful();

        $this->assertDatabaseMissing('messages', ['id' => $old->id]);
        $this->assertDatabaseHas('messages', ['id' => $fresh->id]);
        $this->assertSame(1, Event::count());
    }

    public function test_prune_respects_per_topic_retention_override(): void
    {
        $strict = Topic::factory()->create(['retention_days' => 7]);
        $default = Topic::factory()->create();

        $this->messageWithEvent($strict, now()->subDays(30));
        $kept = $this->messageWithEvent($default, now()->subDays(30));

        $this->artisan('app:prune-events')->assertSuccessful();

        $this->assertSame(0, $strict->messages()->count());
        $this->assertDatabaseHas('messages', ['id' => $kept->id]);
    }

    public function test_prune_keeps_messages_with_recent_activity(): void
    {
        $topic = Topic::factory()->create();
        $message = Message::factory()->for($topic)->create([
            'created_at' => now()->subDays(100),
            'last_event_at' => now()->subDay(),
        ]);

        $this->artisan('app:prune-events')->assertSuccessful();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_prune_leaves_daily_aggregates_alone(): void
    {
        $topic = Topic::factory()->create();
        $this->messageWithEvent($topic, now()->subDays(100));
        DailyAggregate::recount($topic->id, now()->subDays(100));

        $this->artisan('app:prune-events')->assertSuccessful();

        $this->assertSame(0, Event::count());
        $this->assertSame(1, DailyAggregate::count());
    }
}
