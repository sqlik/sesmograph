<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\AlertRule;
use App\Models\ApiToken;
use App\Models\Message;
use App\Models\Topic;
use App\Models\User;
use App\Services\SnsSignatureValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 9 end-to-end run: realistic SNS envelopes and SES payloads from
 * subscription confirmation through ingestion, replay, panel, alerts,
 * and the read API - the whole pipeline in one scenario.
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topic = Topic::factory()->create(['name' => 'shop']);

        $this->mock(SnsSignatureValidator::class)
            ->shouldReceive('isValid')
            ->andReturn(true);
    }

    /** Post an SNS envelope the way SNS delivers it: JSON body, no CSRF. */
    private function sns(array $body): TestResponse
    {
        return $this->call(
            'POST',
            "/webhooks/{$this->topic->webhook_token}",
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($body),
        );
    }

    private function envelope(array $sesEvent): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => fake()->uuid(),
            'TopicArn' => 'arn:aws:sns:eu-west-1:123456789012:shop-ses-events',
            'Subject' => 'Amazon SES Email Event Notification',
            'Message' => json_encode($sesEvent),
            'Timestamp' => now()->toIso8601ZuluString('millisecond'),
            'SignatureVersion' => '1',
            'Signature' => 'dGVzdA==',
            'SigningCertURL' => 'https://sns.eu-west-1.amazonaws.com/SimpleNotificationService-abc.pem',
            'UnsubscribeURL' => 'https://sns.eu-west-1.amazonaws.com/?Action=Unsubscribe',
        ];
    }

    /** A mail object shaped like real SES event publishing output. */
    private function mail(string $messageId, string $recipient, string $subject): array
    {
        return [
            'timestamp' => now()->subMinutes(5)->toIso8601ZuluString('millisecond'),
            'source' => 'orders@shop.example.com',
            'sourceArn' => 'arn:aws:ses:eu-west-1:123456789012:identity/shop.example.com',
            'sendingAccountId' => '123456789012',
            'messageId' => $messageId,
            'destination' => [$recipient],
            'headersTruncated' => false,
            'headers' => [
                ['name' => 'From', 'value' => 'Shop <orders@shop.example.com>'],
                ['name' => 'To', 'value' => $recipient],
                ['name' => 'Subject', 'value' => $subject],
            ],
            'commonHeaders' => [
                'from' => ['Shop <orders@shop.example.com>'],
                'to' => [$recipient],
                'messageId' => '<'.$messageId.'@eu-west-1.amazonses.com>',
                'subject' => $subject,
            ],
            'tags' => [
                'ses:configuration-set' => ['shop-ses'],
                'ses:source-ip' => ['192.0.2.1'],
            ],
        ];
    }

    public function test_subscription_confirmation_is_fetched_automatically(): void
    {
        Http::fake(['sns.eu-west-1.amazonaws.com/*' => Http::response('<ConfirmSubscriptionResponse/>')]);

        $this->sns([
            'Type' => 'SubscriptionConfirmation',
            'MessageId' => fake()->uuid(),
            'Token' => '2336412f37...',
            'TopicArn' => 'arn:aws:sns:eu-west-1:123456789012:shop-ses-events',
            'Message' => 'You have chosen to subscribe to the topic...',
            'SubscribeURL' => 'https://sns.eu-west-1.amazonaws.com/?Action=ConfirmSubscription&Token=abc',
            'Timestamp' => now()->toIso8601ZuluString('millisecond'),
            'SignatureVersion' => '1',
            'Signature' => 'dGVzdA==',
            'SigningCertURL' => 'https://sns.eu-west-1.amazonaws.com/SimpleNotificationService-abc.pem',
        ])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'Action=ConfirmSubscription'));
    }

    public function test_full_lifecycle_from_events_to_panel_alerts_and_api(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response(['ok' => true])]);

        // Alerting: a webhook channel wired to an immediate rule, so the
        // hard bounce below must fire it exactly once.
        $channel = AlertChannel::create([
            'name' => 'n8n',
            'type' => 'webhook',
            'config' => ['url' => 'https://hooks.example.com/sesmograph', 'secret' => 'shh'],
        ]);
        // Cooldown matters here: after-response jobs re-run on later
        // requests inside one test process, and the cooldown absorbs that.
        $rule = AlertRule::create([
            'topic_id' => null,
            'type' => 'immediate',
            'config' => ['triggers' => ['hard_bounce', 'complaint']],
            'cooldown_minutes' => 30,
        ]);
        $rule->channels()->attach($channel);

        // Message A: send -> delivery -> open. Ends up delivered.
        $mailA = $this->mail('0107019-mA-000001', 'anna@example.com', 'Order confirmation #1001');
        $this->sns($this->envelope([
            'eventType' => 'Send',
            'mail' => $mailA,
            'send' => [],
        ]))->assertOk();
        $this->sns($this->envelope([
            'eventType' => 'Delivery',
            'mail' => $mailA,
            'delivery' => [
                'timestamp' => now()->subMinutes(4)->toIso8601ZuluString('millisecond'),
                'processingTimeMillis' => 831,
                'recipients' => ['anna@example.com'],
                'smtpResponse' => '250 2.6.0 Queued mail for delivery',
                'reportingMTA' => 'a8-50.smtp-out.amazonses.com',
            ],
        ]))->assertOk();
        $this->sns($this->envelope([
            'eventType' => 'Open',
            'mail' => $mailA,
            'open' => [
                'timestamp' => now()->subMinutes(2)->toIso8601ZuluString('millisecond'),
                'ipAddress' => '192.0.2.44',
                'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            ],
        ]))->assertOk();

        // Message B: send -> hard bounce with SMTP diagnostics.
        $mailB = $this->mail('0107019-mB-000002', 'gone@example.net', 'Order confirmation #1002');
        $this->sns($this->envelope([
            'eventType' => 'Send',
            'mail' => $mailB,
            'send' => [],
        ]))->assertOk();
        $bounceEnvelope = $this->envelope([
            'eventType' => 'Bounce',
            'mail' => $mailB,
            'bounce' => [
                'feedbackId' => '0107019-fb-1',
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [[
                    'emailAddress' => 'gone@example.net',
                    'action' => 'failed',
                    'status' => '5.1.1',
                    'diagnosticCode' => 'smtp; 550 5.1.1 <gone@example.net>: Recipient address rejected',
                ]],
                'timestamp' => now()->subMinutes(4)->toIso8601ZuluString('millisecond'),
                'reportingMTA' => 'dns; a8-50.smtp-out.amazonses.com',
            ],
        ]);
        $this->sns($bounceEnvelope)->assertOk();

        // SNS delivers at-least-once - replay the bounce verbatim.
        $this->sns($bounceEnvelope)->assertOk();

        // Statuses: open never overrides delivered; bounce wins for B.
        $this->assertDatabaseHas('messages', ['ses_message_id' => '0107019-mA-000001', 'status' => 'delivered']);
        $this->assertDatabaseHas('messages', ['ses_message_id' => '0107019-mB-000002', 'status' => 'bounced']);

        // Replay stored nothing twice: 5 events, one suppression, hits=1.
        $this->assertDatabaseCount('events', 5);
        $this->assertDatabaseHas('suppressed_addresses', [
            'address' => 'gone@example.net', 'reason' => 'bounce', 'hits' => 1,
        ]);

        // Aggregates recounted after-response: 2 sends, 1 delivery, 1 bounce, 1 open today.
        $this->assertDatabaseHas('daily_aggregates', [
            'topic_id' => $this->topic->id,
            'send_count' => 2, 'delivery_count' => 1, 'bounce_count' => 1, 'open_count' => 1,
        ]);

        // The immediate alert fired exactly once, through the webhook channel.
        Http::assertSentCount(1);
        $this->assertDatabaseCount('alerts_log', 1);

        // Panel: dashboard, search, timeline, and suppressed all show the data.
        $admin = User::factory()->withTwoFactor()->create();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/messages?status=bounced')
            ->assertOk()
            ->assertSee('Order confirmation #1002')
            ->assertDontSee('Order confirmation #1001');

        $messageB = Message::query()->where('ses_message_id', '0107019-mB-000002')->firstOrFail();
        $this->actingAs($admin)->get("/messages/{$messageB->id}")
            ->assertOk()
            ->assertSee('Recipient address rejected');

        $this->actingAs($admin)->get('/suppressed')
            ->assertOk()
            ->assertSee('gone@example.net');

        // Read API agrees with the panel.
        [, $token] = ApiToken::issue('e2e');
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/stats?topic=shop', $headers)
            ->assertOk()
            ->assertJsonPath('totals.send', 2)
            ->assertJsonPath('bounce_rate', 50);

        $this->getJson('/api/v1/suppressed?address=gone@example.net', $headers)
            ->assertOk()
            ->assertJsonPath('suppressed', true);

        $this->getJson('/api/v1/messages/0107019-mB-000002', $headers)
            ->assertOk()
            ->assertJsonPath('events.1.type', 'bounce');
    }

    public function test_inactive_topic_returns_410_and_stores_nothing(): void
    {
        $this->topic->update(['active' => false]);

        $this->sns($this->envelope([
            'eventType' => 'Send',
            'mail' => $this->mail('0107019-mC-000003', 'x@example.com', 'Hello'),
            'send' => [],
        ]))->assertStatus(410);

        $this->assertDatabaseCount('events', 0);
    }
}
