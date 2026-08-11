<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\DailyAggregate;
use App\Models\Message;
use App\Models\SuppressedAddress;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiReadTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        [, $this->token] = ApiToken::issue('test-suite');
    }

    private function apiGet(string $uri): TestResponse
    {
        return $this->getJson($uri, ['Authorization' => "Bearer {$this->token}"]);
    }

    private function seedBouncedMessage(Topic $topic, string $recipient = 'alice@example.com'): Message
    {
        $message = Message::factory()->for($topic)->create([
            'subject' => 'Welcome aboard',
            'recipients' => [$recipient],
            'status' => 'bounced',
        ]);

        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'send',
            'occurred_at' => now()->subMinutes(2),
            'payload' => [],
        ]);
        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'bounce',
            'occurred_at' => now()->subMinute(),
            'payload' => [
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bouncedRecipients' => [[
                        'emailAddress' => $recipient,
                        'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
                    ]],
                ],
            ],
        ]);

        return $message;
    }

    public function test_endpoints_require_a_token(): void
    {
        $this->getJson('/api/v1/events')->assertUnauthorized();
        $this->getJson('/api/v1/stats')->assertUnauthorized();
        $this->getJson('/api/v1/suppressed')->assertUnauthorized();
        $this->getJson('/api/v1/messages/some-id')->assertUnauthorized();
    }

    public function test_events_search_filters_by_type_and_query(): void
    {
        $topic = Topic::factory()->create(['name' => 'app']);
        $this->seedBouncedMessage($topic);

        $other = Message::factory()->for($topic)->create(['recipients' => ['bob@example.com']]);
        $other->events()->create([
            'topic_id' => $topic->id, 'type' => 'delivery', 'occurred_at' => now(), 'payload' => [],
        ]);

        $this->apiGet('/api/v1/events')->assertOk()->assertJsonPath('total', 3);

        $response = $this->apiGet('/api/v1/events?type=bounce&q=alice@example.com')->assertOk();
        $response->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.type', 'bounce')
            ->assertJsonPath('data.0.topic', 'app')
            ->assertJsonPath('data.0.details.Diagnostic', 'smtp; 550 5.1.1 user unknown');
    }

    public function test_events_search_rejects_unknown_type_and_topic(): void
    {
        $this->apiGet('/api/v1/events?type=nonsense')->assertStatus(422);
        $this->apiGet('/api/v1/events?topic=missing')->assertStatus(422);
    }

    public function test_message_timeline_returns_events_oldest_first(): void
    {
        $message = $this->seedBouncedMessage(Topic::factory()->create());

        $this->apiGet("/api/v1/messages/{$message->ses_message_id}")
            ->assertOk()
            ->assertJsonPath('ses_message_id', $message->ses_message_id)
            ->assertJsonPath('status', 'bounced')
            ->assertJsonPath('events.0.type', 'send')
            ->assertJsonPath('events.1.type', 'bounce');

        $this->apiGet('/api/v1/messages/no-such-id')->assertNotFound();
    }

    public function test_stats_sums_aggregates_and_derives_rates(): void
    {
        $topic = Topic::factory()->create(['name' => 'app']);
        DailyAggregate::create([
            'topic_id' => $topic->id, 'date' => today()->subDay(),
            'send_count' => 80, 'delivery_count' => 76, 'bounce_count' => 4,
        ]);
        DailyAggregate::create([
            'topic_id' => $topic->id, 'date' => today(),
            'send_count' => 20, 'delivery_count' => 19, 'complaint_count' => 1,
        ]);

        $this->apiGet('/api/v1/stats?topic=app')
            ->assertOk()
            ->assertJsonPath('topic', 'app')
            ->assertJsonPath('totals.send', 100)
            ->assertJsonPath('totals.bounce', 4)
            ->assertJsonPath('bounce_rate', 4)
            ->assertJsonPath('complaint_rate', 1)
            ->assertJsonCount(2, 'days');
    }

    public function test_stats_rejects_inverted_date_range(): void
    {
        $this->apiGet('/api/v1/stats?from=2026-08-10&to=2026-08-01')->assertStatus(422);
    }

    public function test_suppressed_check_answers_for_a_single_address(): void
    {
        $topic = Topic::factory()->create(['name' => 'app']);
        SuppressedAddress::create([
            'topic_id' => $topic->id, 'address' => 'bad@example.com',
            'reason' => 'bounce', 'last_event_at' => now(),
            'last_diagnostic' => 'smtp; 550 5.1.1 user unknown',
        ]);

        $this->apiGet('/api/v1/suppressed?address=Bad@Example.com')
            ->assertOk()
            ->assertJsonPath('suppressed', true)
            ->assertJsonPath('entries.0.topic', 'app')
            ->assertJsonPath('entries.0.reason', 'bounce');

        $this->apiGet('/api/v1/suppressed?address=fine@example.com')
            ->assertOk()
            ->assertJsonPath('suppressed', false)
            ->assertJsonPath('entries', []);
    }

    public function test_suppressed_list_filters_by_reason(): void
    {
        $topic = Topic::factory()->create();
        SuppressedAddress::create([
            'topic_id' => $topic->id, 'address' => 'bounced@example.com',
            'reason' => 'bounce', 'last_event_at' => now(),
        ]);
        SuppressedAddress::create([
            'topic_id' => $topic->id, 'address' => 'complained@example.com',
            'reason' => 'complaint', 'last_event_at' => now(),
        ]);

        $this->apiGet('/api/v1/suppressed?reason=complaint')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.address', 'complained@example.com');
    }
}
