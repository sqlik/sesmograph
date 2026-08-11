<?php

namespace Tests\Feature;

use App\Mcp\Servers\SesmographServer;
use App\Mcp\Tools\GetStats;
use App\Mcp\Tools\SearchEvents;
use App\Models\AlertChannel;
use App\Models\ApiToken;
use App\Models\Message;
use App\Models\MessageContent;
use App\Models\SuppressedAddress;
use App\Models\Topic;
use App\Models\User;
use App\Services\Alerts\AlertSender;
use App\Services\SnsSignatureValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withTwoFactor()->create();
    }

    private function apiHeaders(): array
    {
        [, $plain] = ApiToken::issue('test-service');

        return ['Authorization' => 'Bearer '.$plain];
    }

    private function trustSignatures(): void
    {
        $this->mock(SnsSignatureValidator::class)->shouldReceive('isValid')->andReturn(true);
    }

    private function notification(string $arn, string $messageId = 'ses-1'): array
    {
        return [
            'Type' => 'Notification',
            'TopicArn' => $arn,
            'Message' => json_encode([
                'eventType' => 'Delivery',
                'mail' => ['messageId' => $messageId, 'timestamp' => '2026-08-10T10:00:00.000Z'],
                'delivery' => ['timestamp' => '2026-08-10T10:00:02.000Z'],
            ]),
        ];
    }

    // --- Webhook: TopicArn pinning -------------------------------------

    public function test_first_delivery_pins_the_sns_topic_arn(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->notification('arn:aws:sns:eu-west-1:111:legit'))
            ->assertOk();

        $this->assertSame('arn:aws:sns:eu-west-1:111:legit', $topic->refresh()->sns_topic_arn);
    }

    public function test_notification_from_another_sns_topic_is_rejected(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create(['sns_topic_arn' => 'arn:aws:sns:eu-west-1:111:legit']);

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->notification('arn:aws:sns:eu-west-1:666:forged'))
            ->assertForbidden();

        $this->assertDatabaseCount('events', 0);
    }

    public function test_notification_from_the_pinned_sns_topic_is_accepted(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create(['sns_topic_arn' => 'arn:aws:sns:eu-west-1:111:legit']);

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->notification('arn:aws:sns:eu-west-1:111:legit'))
            ->assertOk();

        $this->assertDatabaseCount('events', 1);
    }

    public function test_malformed_subscribe_url_is_a_400_not_a_500(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", [
            'Type' => 'SubscriptionConfirmation',
            'TopicArn' => 'arn:aws:sns:eu-west-1:111:legit',
            'SubscribeURL' => 'http:///no-host',
        ])->assertStatus(400);
    }

    // --- Message preview: remote asset blocking ------------------------

    public function test_blocked_preview_carries_a_content_security_policy(): void
    {
        $message = Message::factory()->create(['ses_message_id' => 'ses-csp']);
        MessageContent::create([
            'ses_message_id' => 'ses-csp',
            'message_id' => $message->id,
            'html' => '<div style="background:url(https://evil.test/pixel)">Hi</div>',
        ]);

        $this->actingAs($this->admin())->get("/messages/{$message->id}")
            ->assertOk()
            ->assertSee('Content-Security-Policy', false);

        $this->actingAs($this->admin())->get("/messages/{$message->id}?images=1")
            ->assertOk()
            ->assertDontSee('Content-Security-Policy', false);
    }

    // --- CSV exports: formula injection --------------------------------

    public function test_csv_exports_neutralize_spreadsheet_formulas(): void
    {
        $topic = Topic::factory()->create();
        Message::factory()->create([
            'topic_id' => $topic->id,
            'subject' => '=HYPERLINK("https://evil.test")',
        ]);
        SuppressedAddress::forceCreate([
            'topic_id' => $topic->id,
            'address' => 'victim@example.com',
            'reason' => 'bounce',
            'hits' => 1,
            'last_event_at' => now(),
            'last_diagnostic' => '=2+5|cmd',
        ]);

        $messages = $this->actingAs($this->admin())->get('/messages/export')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $messages);

        $suppressed = $this->actingAs($this->admin())->get('/suppressed/export')->streamedContent();
        $this->assertStringContainsString("'=2+5|cmd", $suppressed);
    }

    // --- Content ingest: partial posts and multi-topic fan-out ---------

    public function test_posting_html_later_does_not_erase_stored_text(): void
    {
        $headers = $this->apiHeaders();

        $this->postJson('/api/v1/messages/ses-2/content', ['text' => 'Plain body'], $headers)
            ->assertCreated();

        $this->postJson('/api/v1/messages/ses-2/content', ['html' => '<p>Rich body</p>'], $headers)
            ->assertOk();

        $content = MessageContent::sole();
        $this->assertSame('Plain body', $content->text);
        $this->assertSame('<p>Rich body</p>', $content->html);
    }

    public function test_content_is_shared_between_topics_receiving_the_same_send(): void
    {
        $older = Topic::factory()->create(['name' => 'older']);
        $newer = Topic::factory()->create(['name' => 'newer']);
        $messageA = Message::factory()->create([
            'topic_id' => $older->id,
            'ses_message_id' => 'ses-shared',
            'last_event_at' => now()->subHour(),
        ]);
        $messageB = Message::factory()->create([
            'topic_id' => $newer->id,
            'ses_message_id' => 'ses-shared',
            'last_event_at' => now(),
        ]);

        $this->postJson('/api/v1/messages/ses-shared/content', ['text' => 'Shared body'], $this->apiHeaders())
            ->assertCreated();

        $this->assertSame('Shared body', $messageA->fresh()->content?->text);
        $this->assertSame('Shared body', $messageB->fresh()->content?->text);

        // The timeline lookup resolves the same id deterministically:
        // the most recently active message wins.
        $this->getJson('/api/v1/messages/ses-shared', $this->apiHeaders())
            ->assertOk()
            ->assertJsonPath('topic', 'newer');
    }

    // --- Alert channels: type tampering --------------------------------

    public function test_channel_type_cannot_be_changed_on_update(): void
    {
        $channel = AlertChannel::create([
            'name' => 'Ops mail',
            'type' => 'smtp',
            'config' => [
                'host' => 'smtp.example.com', 'port' => 587,
                'from_address' => 'alerts@example.com', 'to_address' => 'ops@example.com',
            ],
        ]);

        $this->actingAs($this->admin())
            ->put("/settings/alerts/channels/{$channel->id}", [
                'name' => 'Ops mail',
                'type' => 'telegram',
                'config' => [
                    'host' => 'smtp.example.com', 'port' => 587,
                    'from_address' => 'alerts@example.com', 'to_address' => 'ops@example.com',
                ],
            ])
            ->assertRedirect('/settings/alerts');

        $this->assertSame('smtp', $channel->refresh()->type);

        // The settings page must keep rendering (match arms stay exhaustive).
        $this->actingAs($this->admin())->get('/settings/alerts')->assertOk();
    }

    public function test_old_channel_type_is_js_encoded_in_the_form(): void
    {
        $payload = "smtp'};alert(1);//";

        $this->actingAs($this->admin())
            ->from('/settings/alerts/channels/create')
            ->post('/settings/alerts/channels', ['name' => 'x', 'type' => $payload, 'config' => []])
            ->assertRedirect('/settings/alerts/channels/create');

        $this->actingAs($this->admin())
            ->get('/settings/alerts/channels/create')
            ->assertOk()
            ->assertDontSee("'};alert(1)", false);
    }

    // --- Query parameters: arrays and garbage must not 500 -------------

    public function test_array_shaped_api_filters_return_422(): void
    {
        $headers = $this->apiHeaders();

        $this->getJson('/api/v1/events?q[]=x', $headers)->assertStatus(422);
        $this->getJson('/api/v1/events?topic[]=x&from[]=x', $headers)->assertStatus(422);
        $this->getJson('/api/v1/stats?topic[]=x', $headers)->assertStatus(422);
        $this->getJson('/api/v1/suppressed?address[]=x', $headers)->assertStatus(422);
    }

    public function test_array_shaped_panel_filters_are_ignored(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/dashboard?topics[]=1')->assertOk();
        $this->actingAs($admin)->get('/messages?q[]=x&topics[]=1&from=not-a-date&to[]=2')->assertOk();
        $this->actingAs($admin)->get('/suppressed?q[]=x&topics[]=1&reason[]=bounce')->assertOk();
    }

    public function test_activity_page_survives_array_shaped_filters(): void
    {
        AlertChannel::create([
            'name' => 'Ops',
            'type' => 'telegram',
            'config' => ['bot_token' => '1:x', 'chat_id' => '-1'],
        ]);

        // A non-empty alerts list makes the filter form (which echoes the
        // params into hidden inputs) render - the crash path.
        $this->actingAs($this->admin())
            ->get('/settings/activity?topic=1&token[]=x')
            ->assertOk();
    }

    public function test_csv_exports_neutralize_all_untrusted_columns(): void
    {
        $topic = Topic::factory()->create(['name' => 'Ops']);
        // ses_message_id comes from the SNS payload - attacker-influenced.
        Message::factory()->create([
            'topic_id' => $topic->id,
            'ses_message_id' => '=cmd|/c calc',
            'subject' => 'ok',
        ]);

        $csv = $this->actingAs($this->admin())->get('/messages/export')->streamedContent();
        $this->assertStringContainsString("'=cmd|/c calc", $csv);
    }

    public function test_alert_failure_message_redacts_the_telegram_bot_token(): void
    {
        $secret = AlertSender::redact('cURL error to https://api.telegram.org/bot123456:AAExampleSecretToken/sendMessage failed');

        $this->assertStringNotContainsString('AAExampleSecretToken', $secret);
        $this->assertStringContainsString('bot[REDACTED]', $secret);
    }

    public function test_hsts_header_is_sent_over_https(): void
    {
        $this->actingAs($this->admin())
            ->get('https://localhost/dashboard')
            ->assertHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
    }

    public function test_mcp_tools_reject_array_shaped_arguments(): void
    {
        SesmographServer::tool(SearchEvents::class, ['q' => ['x']])
            ->assertHasErrors();

        SesmographServer::tool(GetStats::class, ['topic' => ['x']])
            ->assertHasErrors();
    }

    public function test_overlong_content_id_is_rejected_with_422(): void
    {
        $this->postJson('/api/v1/messages/'.str_repeat('a', 300).'/content',
            ['text' => 'body'], $this->apiHeaders())
            ->assertStatus(422);
    }

    public function test_default_trusted_proxies_ignore_a_spoofed_forwarded_header(): void
    {
        Route::get('/__ip_probe', fn (Request $r) => $r->ip());

        // Two hops: an attacker-supplied 9.9.9.9 ahead of a real 10.0.0.5.
        // REMOTE_ADDR is 127.0.0.1 (trusted) in tests, so with only the
        // loopback trusted the client IP is the rightmost untrusted hop -
        // never the spoofed head that a trustProxies('*') would have taken.
        $response = $this->get('/__ip_probe', ['X-Forwarded-For' => '9.9.9.9, 10.0.0.5']);

        $response->assertOk();
        $this->assertSame('10.0.0.5', $response->getContent());
    }
}
