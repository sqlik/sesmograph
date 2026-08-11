<?php

namespace Tests\Feature;

use App\Models\AlertLog;
use App\Models\AlertRule;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['totp_confirmed_at' => now()]);
    }

    public function test_api_requests_are_logged_with_token_and_status(): void
    {
        [$token, $plain] = ApiToken::issue('logger');

        $this->getJson('/api/v1/events', ['Authorization' => "Bearer {$plain}"])->assertOk();

        $log = ApiRequestLog::sole();
        $this->assertSame($token->id, $log->api_token_id);
        $this->assertSame('GET', $log->method);
        $this->assertSame('/api/v1/events', $log->path);
        $this->assertSame(200, $log->status);
    }

    public function test_unauthenticated_api_requests_are_not_logged(): void
    {
        $this->getJson('/api/v1/events')->assertUnauthorized();

        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_health_endpoint_reports_freshness_and_stays_out_of_the_log(): void
    {
        [, $plain] = ApiToken::issue('monitor');

        $this->getJson('/api/v1/health', ['Authorization' => "Bearer {$plain}"])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('last_event_at', null);

        $this->assertSame(0, ApiRequestLog::count());
    }

    public function test_activity_page_lists_alerts_and_api_requests(): void
    {
        $topic = Topic::factory()->create(['name' => 'acme-app', 'color' => 'mint']);
        $rule = AlertRule::create([
            'topic_id' => $topic->id,
            'type' => 'immediate',
            'config' => ['events' => ['bounce']],
            'cooldown_minutes' => 30,
        ]);
        AlertLog::create([
            'alert_rule_id' => $rule->id,
            'topic_id' => $topic->id,
            'subject' => 'Hard bounce on acme-app',
            'body' => 'alice@example.com bounced',
            'delivery' => ['ops-mail' => 'sent', 'ops-telegram' => 'failed: timeout'],
        ]);

        [$token] = ApiToken::issue('logger');
        ApiRequestLog::create([
            'api_token_id' => $token->id,
            'method' => 'GET',
            'path' => '/api/v1/stats',
            'status' => 200,
            'ip' => '127.0.0.1',
        ]);

        $this->actingAs($this->admin())
            ->get('/settings/activity')
            ->assertOk()
            ->assertSee('Hard bounce on acme-app')
            ->assertSee('ops-mail')
            ->assertSee('ops-telegram')
            ->assertSee('/api/v1/stats')
            ->assertSee('logger');
    }
}
