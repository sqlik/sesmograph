<?php

namespace Tests\Feature;

use App\Mcp\Servers\SesmographServer;
use App\Mcp\Tools\CheckAddress;
use App\Mcp\Tools\GetMessageTimeline;
use App\Mcp\Tools\GetStats;
use App\Mcp\Tools\SearchEvents;
use App\Models\ApiToken;
use App\Models\DailyAggregate;
use App\Models\Message;
use App\Models\SuppressedAddress;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_requires_a_token(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
            ->assertUnauthorized();
    }

    public function test_endpoint_answers_over_http_with_a_token(): void
    {
        [, $token] = ApiToken::issue('mcp-client');

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'sesmograph');
    }

    public function test_search_events_finds_bounces(): void
    {
        $topic = Topic::factory()->create(['name' => 'app']);
        $message = Message::factory()->for($topic)->create(['recipients' => ['alice@example.com']]);
        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'bounce',
            'occurred_at' => now(),
            'payload' => ['bounce' => ['bounceType' => 'Permanent']],
        ]);

        SesmographServer::tool(SearchEvents::class, ['type' => 'bounce'])
            ->assertOk()
            ->assertSee('alice@example.com')
            ->assertSee('"total":1');
    }

    public function test_search_events_reports_unknown_topic(): void
    {
        SesmographServer::tool(SearchEvents::class, ['topic' => 'missing'])
            ->assertHasErrors();
    }

    public function test_get_message_timeline_returns_diagnostics(): void
    {
        $topic = Topic::factory()->create();
        $message = Message::factory()->for($topic)->create(['status' => 'bounced']);
        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'bounce',
            'occurred_at' => now(),
            'payload' => [
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bouncedRecipients' => [[
                        'emailAddress' => 'alice@example.com',
                        'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
                    ]],
                ],
            ],
        ]);

        SesmographServer::tool(GetMessageTimeline::class, ['ses_message_id' => $message->ses_message_id])
            ->assertOk()
            ->assertSee('smtp; 550 5.1.1 user unknown');

        SesmographServer::tool(GetMessageTimeline::class, ['ses_message_id' => 'no-such-id'])
            ->assertHasErrors();
    }

    public function test_get_stats_derives_rates(): void
    {
        $topic = Topic::factory()->create(['name' => 'app']);
        DailyAggregate::create([
            'topic_id' => $topic->id, 'date' => today(),
            'send_count' => 100, 'delivery_count' => 95, 'bounce_count' => 5,
        ]);

        SesmographServer::tool(GetStats::class, ['topic' => 'app'])
            ->assertOk()
            ->assertSee('"bounce_rate":5');
    }

    public function test_check_address_flags_suppressed_recipients(): void
    {
        SuppressedAddress::create([
            'topic_id' => Topic::factory()->create()->id,
            'address' => 'bad@example.com',
            'reason' => 'complaint',
            'last_event_at' => now(),
        ]);

        SesmographServer::tool(CheckAddress::class, ['address' => 'bad@example.com'])
            ->assertOk()
            ->assertSee('"suppressed":true');

        SesmographServer::tool(CheckAddress::class, ['address' => 'fine@example.com'])
            ->assertOk()
            ->assertSee('"suppressed":false');

        SesmographServer::tool(CheckAddress::class, [])
            ->assertHasErrors();
    }
}
