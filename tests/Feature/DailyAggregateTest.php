<?php

namespace Tests\Feature;

use App\Models\DailyAggregate;
use App\Models\Message;
use App\Models\Topic;
use App\Services\SesEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyAggregateTest extends TestCase
{
    use RefreshDatabase;

    private function sesEvent(string $type, string $messageId = 'ses-message-1'): array
    {
        return [
            'eventType' => ucfirst($type),
            'mail' => [
                'messageId' => $messageId,
                'timestamp' => now()->toIso8601String(),
                'commonHeaders' => ['subject' => 'Test', 'to' => ['to@example.com']],
            ],
        ];
    }

    public function test_processing_an_event_refreshes_the_daily_aggregate(): void
    {
        $topic = Topic::factory()->create();

        app(SesEventProcessor::class)->process($topic, $this->sesEvent('send'));
        app(SesEventProcessor::class)->process($topic, $this->sesEvent('delivery'));

        $aggregate = DailyAggregate::query()->sole();

        $this->assertSame($topic->id, $aggregate->topic_id);
        $this->assertSame(today()->toDateString(), $aggregate->date->toDateString());
        $this->assertSame(1, $aggregate->send_count);
        $this->assertSame(1, $aggregate->delivery_count);
    }

    public function test_replayed_event_does_not_double_count(): void
    {
        $topic = Topic::factory()->create();
        $event = $this->sesEvent('send');
        $event['mail']['timestamp'] = now()->startOfMinute()->toIso8601String();

        app(SesEventProcessor::class)->process($topic, $event);
        app(SesEventProcessor::class)->process($topic, $event);

        $this->assertSame(1, DailyAggregate::query()->sole()->send_count);
    }

    public function test_incremental_counts_match_a_full_recount(): void
    {
        $topic = Topic::factory()->create();

        app(SesEventProcessor::class)->process($topic, $this->sesEvent('send', 'ses-message-1'));
        app(SesEventProcessor::class)->process($topic, $this->sesEvent('delivery', 'ses-message-1'));
        app(SesEventProcessor::class)->process($topic, $this->sesEvent('send', 'ses-message-2'));
        app(SesEventProcessor::class)->process($topic, $this->sesEvent('bounce', 'ses-message-2'));

        $incremental = DailyAggregate::query()->sole()->only(['send_count', 'delivery_count', 'bounce_count']);

        DailyAggregate::recount($topic->id, today());

        $this->assertSame(['send_count' => 2, 'delivery_count' => 1, 'bounce_count' => 1], $incremental);
        $this->assertSame($incremental, DailyAggregate::query()->sole()->only(['send_count', 'delivery_count', 'bounce_count']));
    }

    public function test_recount_is_idempotent(): void
    {
        $topic = Topic::factory()->create();
        $message = Message::factory()->for($topic)->create();
        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'bounce',
            'occurred_at' => now(),
            'payload' => [],
        ]);

        DailyAggregate::recount($topic->id, today());
        DailyAggregate::recount($topic->id, today());

        $aggregate = DailyAggregate::query()->sole();

        $this->assertSame(1, $aggregate->bounce_count);
        $this->assertSame(0, $aggregate->send_count);
    }

    public function test_rebuild_command_covers_every_topic_day_on_record(): void
    {
        $topic = Topic::factory()->create();
        $message = Message::factory()->for($topic)->create();

        foreach ([now(), now()->subDays(3)] as $when) {
            $message->events()->create([
                'topic_id' => $topic->id,
                'type' => 'send',
                'occurred_at' => $when,
                'payload' => [],
            ]);
        }

        $this->artisan('app:rebuild-aggregates')->assertSuccessful();

        $this->assertSame(2, DailyAggregate::count());
        $this->assertSame(2, (int) DailyAggregate::sum('send_count'));
    }

    public function test_rates_derive_from_counters(): void
    {
        $aggregate = new DailyAggregate(['send_count' => 200, 'bounce_count' => 12, 'complaint_count' => 1]);

        $this->assertSame(6.0, $aggregate->bounceRate());
        $this->assertSame(0.5, $aggregate->complaintRate());
        $this->assertNull((new DailyAggregate(['send_count' => 0]))->bounceRate());
    }
}
