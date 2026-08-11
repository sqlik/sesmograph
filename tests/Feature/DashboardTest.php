<?php

namespace Tests\Feature;

use App\Models\DailyAggregate;
use App\Models\Message;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['totp_confirmed_at' => now()]);
    }

    private function seedTraffic(Topic $topic): void
    {
        $message = Message::factory()->for($topic)->create();

        foreach (['send', 'send', 'delivery', 'bounce'] as $offset => $type) {
            $message->events()->create([
                'topic_id' => $topic->id,
                'type' => $type,
                'occurred_at' => now()->subMinutes(30 - $offset),
                'payload' => [],
            ]);
        }

        DailyAggregate::recount($topic->id, today());
    }

    public function test_global_dashboard_shows_stats_volume_and_activity(): void
    {
        $topic = Topic::factory()->create(['name' => 'acme-app']);
        $this->seedTraffic($topic);

        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Sent, 7 days')
            ->assertSee('Volume, last 30 days')
            ->assertSee('data-chart="volume"', false)
            ->assertSee('Recent activity')
            ->assertSee('acme-app');
    }

    public function test_dashboard_filters_activity_by_selected_topics(): void
    {
        $app = Topic::factory()->create(['name' => 'acme-app', 'color' => 'mint']);
        $billing = Topic::factory()->create(['name' => 'acme-billing', 'color' => 'ember']);
        $this->seedTraffic($app);

        $billingMessage = Message::factory()->for($billing)->create(['subject' => 'Invoice #77']);
        $billingMessage->events()->create([
            'topic_id' => $billing->id,
            'type' => 'send',
            'occurred_at' => now()->subMinutes(5),
            'payload' => [],
        ]);
        DailyAggregate::recount($billing->id, today());

        $this->actingAs($this->admin())
            ->get('/dashboard?topics='.$app->id)
            ->assertOk()
            ->assertDontSee('Invoice #77')
            ->assertSee('aria-pressed="true"', false);
    }

    public function test_topic_dashboard_shows_counters_rates_and_thresholds(): void
    {
        $topic = Topic::factory()->create(['name' => 'acme-app']);
        $this->seedTraffic($topic);

        $this->actingAs($this->admin())
            ->get("/topics/{$topic->id}")
            ->assertOk()
            ->assertSee('Sent, last hour')
            ->assertSee('Bounce rate')
            ->assertSee('Complaint rate')
            ->assertSee('data-threshold="5"', false)
            ->assertSee('data-threshold="0.1"', false)
            ->assertSee('Event types')
            ->assertSee('Recent activity');
    }
}
