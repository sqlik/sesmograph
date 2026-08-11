<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\AlertLog;
use App\Models\AlertRule;
use App\Models\Message;
use App\Models\Topic;
use App\Services\Alerts\AlertSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SilenceAlertTest extends TestCase
{
    use RefreshDatabase;

    private function rule(int $hours = 24): AlertRule
    {
        $channel = AlertChannel::create([
            'name' => 'ops-webhook',
            'type' => 'webhook',
            'config' => ['url' => 'https://example.com/hook'],
        ]);

        $rule = AlertRule::create([
            'topic_id' => null,
            'type' => 'silence',
            'config' => ['hours' => $hours],
            'cooldown_minutes' => 60,
        ]);
        $rule->channels()->attach($channel);

        return $rule;
    }

    private function seedEvent(Topic $topic, string $occurredAt): void
    {
        Message::factory()->for($topic)->create()->events()->create([
            'topic_id' => $topic->id,
            'type' => 'send',
            'occurred_at' => $occurredAt,
            'payload' => [],
        ]);
    }

    public function test_fires_for_a_topic_that_went_quiet(): void
    {
        $this->mock(AlertSender::class)->shouldReceive('send')->once();

        $topic = Topic::factory()->create(['name' => 'acme-app']);
        $this->seedEvent($topic, now()->subHours(30)->toDateTimeString());
        $this->rule(24);

        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $log = AlertLog::sole();
        $this->assertStringContainsString('No events on acme-app', $log->subject);
        $this->assertSame(24, $log->context['hours']);
    }

    public function test_skips_fresh_topics_and_topics_without_any_events(): void
    {
        $this->mock(AlertSender::class)->shouldReceive('send')->never();

        $fresh = Topic::factory()->create();
        $this->seedEvent($fresh, now()->subHours(2)->toDateTimeString());
        Topic::factory()->create(); // never received anything - not connected yet
        $this->rule(24);

        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $this->assertSame(0, AlertLog::count());
    }

    public function test_cooldown_prevents_repeat_alerts(): void
    {
        $this->mock(AlertSender::class)->shouldReceive('send')->once();

        $topic = Topic::factory()->create();
        $this->seedEvent($topic, now()->subHours(30)->toDateTimeString());
        $this->rule(24);

        $this->artisan('app:evaluate-alerts')->assertSuccessful();
        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $this->assertSame(1, AlertLog::count());
    }
}
