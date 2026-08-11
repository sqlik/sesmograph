<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\SuppressedAddress;
use App\Models\Topic;
use App\Models\User;
use App\Services\SnsSignatureValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuppressedAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->withTwoFactor()->create();
    }

    private function ingest(Topic $topic, array $sesEvent): void
    {
        $this->mock(SnsSignatureValidator::class)
            ->shouldReceive('isValid')
            ->andReturn(true);

        $this->postJson("/webhooks/{$topic->webhook_token}", [
            'Type' => 'Notification',
            'MessageId' => fake()->uuid(),
            'TopicArn' => 'arn:aws:sns:eu-west-1:123456789012:test-ses-events',
            'Message' => json_encode($sesEvent),
            'Timestamp' => now()->toIso8601String(),
            'SignatureVersion' => '1',
            'Signature' => 'irrelevant-in-tests',
            'SigningCertURL' => 'https://sns.eu-west-1.amazonaws.com/cert.pem',
        ])->assertOk();
    }

    private function sesEvent(string $type, array $detail, string $messageId = 'ses-message-1'): array
    {
        $detailKey = Str::camel(Str::snake($type));

        return [
            'eventType' => $type,
            'mail' => [
                'timestamp' => '2026-08-10T10:00:00.000Z',
                'messageId' => $messageId,
                'source' => 'no-reply@example.com',
                'destination' => ['user@example.com'],
            ],
            $detailKey => $detail + ['timestamp' => '2026-08-10T10:00:05.000Z'],
        ];
    }

    private function hardBounce(string $address, string $messageId = 'ses-message-1'): array
    {
        return $this->sesEvent('Bounce', [
            'bounceType' => 'Permanent',
            'bounceSubType' => 'General',
            'bouncedRecipients' => [[
                'emailAddress' => $address,
                'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
            ]],
        ], $messageId);
    }

    public function test_hard_bounce_suppresses_the_recipient(): void
    {
        $topic = Topic::factory()->create();

        $this->ingest($topic, $this->hardBounce('Bad.User@Example.com'));

        $this->assertDatabaseHas('suppressed_addresses', [
            'topic_id' => $topic->id,
            'address' => 'bad.user@example.com',
            'reason' => 'bounce',
            'hits' => 1,
            'last_diagnostic' => 'smtp; 550 5.1.1 user unknown',
        ]);
    }

    public function test_transient_bounce_does_not_suppress(): void
    {
        $topic = Topic::factory()->create();

        $this->ingest($topic, $this->sesEvent('Bounce', [
            'bounceType' => 'Transient',
            'bounceSubType' => 'MailboxFull',
            'bouncedRecipients' => [['emailAddress' => 'full@example.com']],
        ]));

        $this->assertDatabaseCount('suppressed_addresses', 0);
    }

    public function test_complaint_suppresses_the_recipient(): void
    {
        $topic = Topic::factory()->create();

        $this->ingest($topic, $this->sesEvent('Complaint', [
            'complaintFeedbackType' => 'abuse',
            'complainedRecipients' => [['emailAddress' => 'angry@example.com']],
        ]));

        $this->assertDatabaseHas('suppressed_addresses', [
            'topic_id' => $topic->id,
            'address' => 'angry@example.com',
            'reason' => 'complaint',
        ]);
    }

    public function test_sns_replay_does_not_double_count(): void
    {
        $topic = Topic::factory()->create();

        $this->ingest($topic, $this->hardBounce('bad@example.com'));
        $this->ingest($topic, $this->hardBounce('bad@example.com'));

        $this->assertDatabaseHas('suppressed_addresses', ['address' => 'bad@example.com', 'hits' => 1]);
    }

    public function test_repeat_bounce_of_a_different_message_increments_hits(): void
    {
        $topic = Topic::factory()->create();

        $this->ingest($topic, $this->hardBounce('bad@example.com', 'ses-message-1'));

        $second = $this->hardBounce('bad@example.com', 'ses-message-2');
        $second['bounce']['timestamp'] = '2026-08-10T11:00:00.000Z';
        $this->ingest($topic, $second);

        $this->assertDatabaseHas('suppressed_addresses', ['address' => 'bad@example.com', 'hits' => 2]);
    }

    public function test_guest_cannot_reach_the_suppressed_list(): void
    {
        $this->get('/suppressed')->assertRedirect('/login');
    }

    public function test_index_lists_and_filters_by_reason(): void
    {
        $topic = Topic::factory()->create();
        SuppressedAddress::query()->create([
            'topic_id' => $topic->id, 'address' => 'bounced@example.com',
            'reason' => 'bounce', 'last_event_at' => now(),
        ]);
        SuppressedAddress::query()->create([
            'topic_id' => $topic->id, 'address' => 'complained@example.com',
            'reason' => 'complaint', 'last_event_at' => now(),
        ]);

        $this->actingAs($this->admin)->get('/suppressed')
            ->assertOk()
            ->assertSee('bounced@example.com')
            ->assertSee('complained@example.com');

        $this->actingAs($this->admin)->get('/suppressed?reason=complaint')
            ->assertOk()
            ->assertSee('complained@example.com')
            ->assertDontSee('bounced@example.com');
    }

    public function test_entry_can_be_removed(): void
    {
        $entry = SuppressedAddress::query()->create([
            'topic_id' => Topic::factory()->create()->id,
            'address' => 'pardoned@example.com',
            'reason' => 'bounce',
            'last_event_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete("/suppressed/{$entry->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('suppressed_addresses', 0);
    }

    public function test_suppressed_export_streams_filtered_csv(): void
    {
        $topic = Topic::factory()->create(['name' => 'demo-app']);
        SuppressedAddress::query()->create([
            'topic_id' => $topic->id, 'address' => 'bounced@example.com',
            'reason' => 'bounce', 'last_event_at' => now(),
        ]);
        SuppressedAddress::query()->create([
            'topic_id' => $topic->id, 'address' => 'complained@example.com',
            'reason' => 'complaint', 'last_event_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get('/suppressed/export?reason=bounce');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('suppressed-addresses.csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('address,topic,reason,hits,last_event_at,diagnostic', $csv);
        $this->assertStringContainsString('bounced@example.com,demo-app,bounce', $csv);
        $this->assertStringNotContainsString('complained@example.com', $csv);
    }

    public function test_messages_export_streams_filtered_csv(): void
    {
        Message::factory()->create(['subject' => 'Delivered mail', 'status' => 'delivered', 'last_event_at' => now()]);
        Message::factory()->create(['subject' => 'Bounced mail', 'status' => 'bounced', 'last_event_at' => now()]);

        $response = $this->actingAs($this->admin)->get('/messages/export?status=bounced');

        $response->assertOk()->assertDownload('messages.csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Bounced mail', $csv);
        $this->assertStringNotContainsString('Delivered mail', $csv);
    }

    public function test_rebuild_command_replays_events(): void
    {
        $topic = Topic::factory()->create();
        $this->ingest($topic, $this->hardBounce('bad@example.com'));

        SuppressedAddress::query()->delete();

        $this->artisan('app:rebuild-suppressed')->assertSuccessful();

        $this->assertDatabaseHas('suppressed_addresses', [
            'address' => 'bad@example.com',
            'reason' => 'bounce',
            'hits' => 1,
        ]);
    }
}
