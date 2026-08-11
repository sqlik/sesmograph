<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\AlertLog;
use App\Models\AlertRule;
use App\Models\Message;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function thresholdRule(?Topic $topic, array $overrides = []): AlertRule
    {
        $rule = AlertRule::create([
            'topic_id' => $topic?->id,
            'type' => 'threshold',
            'config' => $overrides + [
                'metric' => 'bounce_rate',
                'threshold' => 5.0,
                'window_minutes' => 60,
                'min_sends' => 10,
            ],
            'cooldown_minutes' => 30,
        ]);

        $rule->channels()->attach(AlertChannel::create([
            'name' => 'Ops Telegram',
            'type' => 'telegram',
            'config' => ['bot_token' => 'token', 'chat_id' => '-1'],
        ]));

        return $rule;
    }

    private function seedWindow(Topic $topic, int $sends, int $bounces): void
    {
        $message = Message::factory()->for($topic)->create();

        for ($i = 1; $i <= $sends; $i++) {
            $message->events()->create([
                'topic_id' => $topic->id,
                'type' => 'send',
                'occurred_at' => now()->subMinutes(5)->subSeconds($i),
                'payload' => [],
            ]);
        }

        for ($i = 1; $i <= $bounces; $i++) {
            $message->events()->create([
                'topic_id' => $topic->id,
                'type' => 'bounce',
                'occurred_at' => now()->subMinutes(4)->subSeconds($i),
                'payload' => [],
            ]);
        }
    }

    public function test_rate_over_threshold_fires_an_alert(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $topic = Topic::factory()->create(['name' => 'acme-app']);
        $this->thresholdRule($topic);
        $this->seedWindow($topic, sends: 20, bounces: 3); // 15%

        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $log = AlertLog::sole();
        $this->assertSame('Bounce rate 15.00% on acme-app', $log->subject);
        $this->assertEquals(15, $log->context['rate']);
    }

    public function test_rate_under_threshold_stays_quiet(): void
    {
        Http::fake();

        $topic = Topic::factory()->create();
        $this->thresholdRule($topic);
        $this->seedWindow($topic, sends: 100, bounces: 2); // 2%

        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $this->assertSame(0, AlertLog::count());
    }

    public function test_min_sends_guards_quiet_hours(): void
    {
        Http::fake();

        $topic = Topic::factory()->create();
        $this->thresholdRule($topic);
        $this->seedWindow($topic, sends: 2, bounces: 1); // 50%, but only 2 sends

        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $this->assertSame(0, AlertLog::count());
    }

    public function test_cooldown_prevents_repeat_alerts_between_runs(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $topic = Topic::factory()->create();
        $this->thresholdRule($topic);
        $this->seedWindow($topic, sends: 20, bounces: 3);

        $this->artisan('app:evaluate-alerts')->assertSuccessful();
        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $this->assertSame(1, AlertLog::count());
    }

    public function test_all_topics_rule_evaluates_each_topic_separately(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $noisy = Topic::factory()->create(['name' => 'noisy']);
        $calm = Topic::factory()->create(['name' => 'calm']);
        $this->thresholdRule(null);
        $this->seedWindow($noisy, sends: 20, bounces: 3);
        $this->seedWindow($calm, sends: 20, bounces: 0);

        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $log = AlertLog::sole();
        $this->assertSame($noisy->id, $log->topic_id);
    }

    public function test_complaint_rate_metric_uses_complaints(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $topic = Topic::factory()->create(['name' => 'acme-app']);
        $this->thresholdRule($topic, ['metric' => 'complaint_rate', 'threshold' => 0.1]);

        $message = Message::factory()->for($topic)->create();

        foreach (range(1, 20) as $i) {
            $message->events()->create([
                'topic_id' => $topic->id,
                'type' => 'send',
                'occurred_at' => now()->subMinutes(5)->subSeconds($i),
                'payload' => [],
            ]);
        }

        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'complaint',
            'occurred_at' => now()->subMinutes(3),
            'payload' => [],
        ]);

        $this->artisan('app:evaluate-alerts')->assertSuccessful();

        $this->assertSame('Complaint rate 5.00% on acme-app', AlertLog::sole()->subject);
    }
}
