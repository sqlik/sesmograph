<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_shows_events_with_bounce_diagnostics(): void
    {
        $admin = User::factory()->withTwoFactor()->create();

        $message = Message::factory()->create([
            'subject' => 'Welcome aboard',
            'status' => 'bounced',
        ]);

        Event::factory()->for($message)->create([
            'topic_id' => $message->topic_id,
            'type' => 'send',
            'occurred_at' => now()->subMinutes(2),
        ]);

        Event::factory()->for($message)->create([
            'topic_id' => $message->topic_id,
            'type' => 'bounce',
            'occurred_at' => now()->subMinute(),
            'payload' => [
                'eventType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bounceSubType' => 'General',
                    'bouncedRecipients' => [[
                        'emailAddress' => 'gone@example.com',
                        'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
                        'status' => '5.1.1',
                    ]],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get("/messages/{$message->id}")
            ->assertOk()
            ->assertSee('Welcome aboard')
            ->assertSee('Timeline')
            ->assertSee('Send')
            ->assertSee('Bounce · Permanent')
            ->assertSee('550 5.1.1 user unknown')
            ->assertSee($message->ses_message_id);
    }

    public function test_delivery_details_show_smtp_response(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $message = Message::factory()->create();

        Event::factory()->for($message)->create([
            'topic_id' => $message->topic_id,
            'type' => 'delivery',
            'payload' => [
                'delivery' => [
                    'smtpResponse' => '250 2.0.0 OK',
                    'processingTimeMillis' => 831,
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get("/messages/{$message->id}")
            ->assertOk()
            ->assertSee('250 2.0.0 OK')
            ->assertSee('831 ms');
    }
}
