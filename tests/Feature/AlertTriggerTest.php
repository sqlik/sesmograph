<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\AlertLog;
use App\Models\AlertRule;
use App\Models\Topic;
use App\Services\SesEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function telegramChannel(): AlertChannel
    {
        return AlertChannel::create([
            'name' => 'Ops Telegram',
            'type' => 'telegram',
            'config' => ['bot_token' => 'token', 'chat_id' => '-1'],
        ]);
    }

    private function immediateRule(?Topic $topic = null, array $triggers = ['hard_bounce', 'complaint'], int $cooldown = 30): AlertRule
    {
        $rule = AlertRule::create([
            'topic_id' => $topic?->id,
            'type' => 'immediate',
            'config' => ['triggers' => $triggers],
            'cooldown_minutes' => $cooldown,
        ]);

        $rule->channels()->attach($this->telegramChannel());

        return $rule;
    }

    private function processBounce(Topic $topic, string $bounceType, string $messageId, ?string $occurredAt = null): void
    {
        app(SesEventProcessor::class)->process($topic, [
            'eventType' => 'Bounce',
            'mail' => [
                'messageId' => $messageId,
                'timestamp' => $occurredAt ?? now()->toIso8601String(),
                'commonHeaders' => ['subject' => 'Weekly digest', 'to' => ['to@example.com']],
            ],
            'bounce' => [
                'bounceType' => $bounceType,
                'bouncedRecipients' => [['emailAddress' => 'to@example.com', 'diagnosticCode' => 'smtp; 550 user unknown']],
            ],
        ]);

        $this->app->terminate();
    }

    public function test_hard_bounce_fires_immediate_alert(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $topic = Topic::factory()->create(['name' => 'acme-app']);
        $this->immediateRule($topic);

        $this->processBounce($topic, 'Permanent', 'ses-1');

        $log = AlertLog::sole();
        $this->assertSame('Hard bounce on acme-app', $log->subject);
        $this->assertSame(['Ops Telegram' => 'sent'], $log->delivery);
        $this->assertStringContainsString('550 user unknown', $log->body);

        Http::assertSent(fn ($request) => str_contains($request['text'], 'Hard bounce on acme-app'));
    }

    public function test_soft_bounce_does_not_fire(): void
    {
        Http::fake();

        $topic = Topic::factory()->create();
        $this->immediateRule($topic);

        $this->processBounce($topic, 'Transient', 'ses-1');

        $this->assertSame(0, AlertLog::count());
        Http::assertNothingSent();
    }

    public function test_cooldown_suppresses_a_burst(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $topic = Topic::factory()->create();
        $this->immediateRule($topic);

        $this->processBounce($topic, 'Permanent', 'ses-1');
        $this->processBounce($topic, 'Permanent', 'ses-2', now()->addMinute()->toIso8601String());

        $this->assertSame(1, AlertLog::count());
        Http::assertSentCount(1);
    }

    public function test_all_topics_rule_matches_any_topic(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->immediateRule(null, ['hard_bounce']);
        $topic = Topic::factory()->create();

        $this->processBounce($topic, 'Permanent', 'ses-1');

        $this->assertSame(1, AlertLog::count());
    }

    public function test_disabled_rule_stays_quiet(): void
    {
        Http::fake();

        $topic = Topic::factory()->create();
        $this->immediateRule($topic)->update(['enabled' => false]);

        $this->processBounce($topic, 'Permanent', 'ses-1');

        $this->assertSame(0, AlertLog::count());
        Http::assertNothingSent();
    }

    public function test_failed_delivery_is_recorded_in_the_log(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('nope', 500)]);

        $topic = Topic::factory()->create();
        $this->immediateRule($topic);

        $this->processBounce($topic, 'Permanent', 'ses-1');

        $log = AlertLog::sole();
        $this->assertStringStartsWith('failed:', $log->delivery['Ops Telegram']);
    }
}
