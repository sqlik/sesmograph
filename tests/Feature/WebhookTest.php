<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Services\SnsSignatureValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function trustSignatures(bool $valid = true): void
    {
        $this->mock(SnsSignatureValidator::class)
            ->shouldReceive('isValid')
            ->andReturn($valid);
    }

    private function snsEnvelope(array $sesEvent): array
    {
        return [
            'Type' => 'Notification',
            'MessageId' => fake()->uuid(),
            'TopicArn' => 'arn:aws:sns:eu-west-1:123456789012:test-ses-events',
            'Message' => json_encode($sesEvent),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'Signature' => 'irrelevant-in-tests',
            'SigningCertURL' => 'https://sns.eu-west-1.amazonaws.com/cert.pem',
        ];
    }

    private function sesEvent(string $type, array $detail = [], string $messageId = 'ses-message-1'): array
    {
        $detailKey = Str::camel(Str::snake($type));

        return [
            'eventType' => $type,
            'mail' => [
                'timestamp' => '2026-08-10T10:00:00.000Z',
                'messageId' => $messageId,
                'source' => 'no-reply@example.com',
                'destination' => ['user@example.com'],
                'commonHeaders' => [
                    'from' => ['App <no-reply@example.com>'],
                    'to' => ['user@example.com'],
                    'subject' => 'Welcome to the app',
                ],
            ],
            $detailKey => $detail + ['timestamp' => '2026-08-10T10:00:05.000Z'],
        ];
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->postJson('/webhooks/does-not-exist', ['Type' => 'Notification'])
            ->assertNotFound();
    }

    public function test_inactive_topic_returns_410(): void
    {
        $topic = Topic::factory()->inactive()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", ['Type' => 'Notification'])
            ->assertStatus(410);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $this->trustSignatures(false);
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope($this->sesEvent('Delivery')))
            ->assertForbidden();

        $this->assertDatabaseCount('events', 0);
    }

    public function test_malformed_body_returns_400(): void
    {
        $topic = Topic::factory()->create();

        $this->call('POST', "/webhooks/{$topic->webhook_token}", content: 'not-json')
            ->assertStatus(400);
    }

    public function test_subscription_confirmation_is_fetched_automatically(): void
    {
        $this->trustSignatures();
        Http::fake(['*' => Http::response('ok')]);

        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", [
            'Type' => 'SubscriptionConfirmation',
            'SubscribeURL' => 'https://sns.eu-west-1.amazonaws.com/?Action=ConfirmSubscription&Token=abc',
            'TopicArn' => 'arn:aws:sns:eu-west-1:123456789012:test-ses-events',
        ])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'ConfirmSubscription'));
    }

    public function test_subscription_confirmation_rejects_non_aws_urls(): void
    {
        $this->trustSignatures();
        Http::fake();

        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", [
            'Type' => 'SubscriptionConfirmation',
            'SubscribeURL' => 'https://evil.example.com/steal',
        ])->assertStatus(400);

        Http::assertNothingSent();
    }

    public function test_delivery_notification_stores_message_and_event(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope($this->sesEvent('Delivery')))
            ->assertOk();

        $this->assertDatabaseHas('messages', [
            'topic_id' => $topic->id,
            'ses_message_id' => 'ses-message-1',
            'subject' => 'Welcome to the app',
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('events', ['topic_id' => $topic->id, 'type' => 'delivery']);
    }

    public function test_event_time_is_stored_in_the_app_timezone(): void
    {
        config(['app.timezone' => 'Europe/Warsaw']);
        $this->trustSignatures();
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope($this->sesEvent('Delivery')))
            ->assertOk();

        // 10:00:05 UTC from SES is 12:00:05 in Warsaw (CEST).
        $this->assertDatabaseHas('events', [
            'topic_id' => $topic->id,
            'occurred_at' => '2026-08-10 12:00:05',
        ]);
    }

    public function test_duplicate_notification_is_idempotent(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create();
        $envelope = $this->snsEnvelope($this->sesEvent('Delivery'));

        $this->postJson("/webhooks/{$topic->webhook_token}", $envelope)->assertOk();
        $this->postJson("/webhooks/{$topic->webhook_token}", $envelope)->assertOk();

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_bounce_after_send_updates_status(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope(
            $this->sesEvent('Send', ['timestamp' => '2026-08-10T10:00:00.000Z']),
        ));
        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope(
            $this->sesEvent('Bounce', [
                'timestamp' => '2026-08-10T10:00:10.000Z',
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
            ]),
        ));

        $this->assertDatabaseHas('messages', ['ses_message_id' => 'ses-message-1', 'status' => 'bounced']);
        $this->assertDatabaseCount('events', 2);
    }

    public function test_open_does_not_change_delivery_status(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope($this->sesEvent('Delivery')));
        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope(
            $this->sesEvent('Open', ['timestamp' => '2026-08-10T10:05:00.000Z']),
        ));

        $this->assertDatabaseHas('messages', ['ses_message_id' => 'ses-message-1', 'status' => 'delivered']);
        $this->assertDatabaseCount('events', 2);
    }

    public function test_delivery_delay_event_type_is_normalized(): void
    {
        $this->trustSignatures();
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", $this->snsEnvelope(
            $this->sesEvent('DeliveryDelay', ['delayType' => 'MailboxFull']),
        ))->assertOk();

        $this->assertDatabaseHas('events', ['type' => 'delivery_delay']);
        $this->assertDatabaseHas('messages', ['status' => 'delayed']);
    }
}
