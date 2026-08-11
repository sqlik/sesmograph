<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Message;
use App\Models\MessageContent;
use App\Models\Topic;
use App\Models\User;
use App\Services\SnsSignatureValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageContentTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        [, $plain] = ApiToken::issue('test-service');

        return $plain;
    }

    public function test_content_endpoint_requires_a_valid_token(): void
    {
        $this->postJson('/api/v1/messages/ses-1/content', ['html' => '<p>Hi</p>'])
            ->assertUnauthorized();

        $this->postJson('/api/v1/messages/ses-1/content', ['html' => '<p>Hi</p>'], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertUnauthorized();
    }

    public function test_content_is_stored_and_linked_to_an_existing_message(): void
    {
        $message = Message::factory()->create(['ses_message_id' => 'ses-1']);

        $this->postJson('/api/v1/messages/ses-1/content', [
            'html' => '<p>Hello Alice</p>',
            'text' => 'Hello Alice',
        ], ['Authorization' => 'Bearer '.$this->token()])
            ->assertCreated();

        $content = MessageContent::sole();
        $this->assertSame($message->id, $content->message_id);
        $this->assertSame('<p>Hello Alice</p>', $content->html);
        $this->assertSame('Hello Alice', $content->text);
    }

    public function test_content_arriving_before_events_is_adopted_by_the_message(): void
    {
        $this->postJson('/api/v1/messages/ses-early/content', [
            'html' => '<p>Early bird</p>',
        ], ['Authorization' => 'Bearer '.$this->token()])
            ->assertCreated();

        $this->assertNull(MessageContent::sole()->message_id);

        // First SES event arrives afterwards.
        $this->mock(SnsSignatureValidator::class)->shouldReceive('isValid')->andReturn(true);
        $topic = Topic::factory()->create();

        $this->postJson("/webhooks/{$topic->webhook_token}", [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Delivery',
                'mail' => ['messageId' => 'ses-early', 'timestamp' => '2026-08-10T10:00:00.000Z'],
                'delivery' => ['timestamp' => '2026-08-10T10:00:02.000Z'],
            ]),
        ])->assertOk();

        $message = Message::sole();
        $this->assertSame($message->id, MessageContent::sole()->message_id);
    }

    public function test_content_requires_html_or_text(): void
    {
        $this->postJson('/api/v1/messages/ses-1/content', [], [
            'Authorization' => 'Bearer '.$this->token(),
        ])->assertStatus(422);
    }

    public function test_message_page_shows_content_with_blocked_remote_images(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $message = Message::factory()->create(['ses_message_id' => 'ses-1']);

        MessageContent::create([
            'ses_message_id' => 'ses-1',
            'message_id' => $message->id,
            'html' => '<p>Hello</p><img src="https://tracker.example.com/pixel.gif">',
            'text' => 'Hello',
        ]);

        $response = $this->actingAs($admin)->get("/messages/{$message->id}")->assertOk();

        $response->assertSee('Load remote images');
        $this->assertStringContainsString('data-blocked-src', $response->content());
        $this->assertStringNotContainsString('srcdoc="&lt;p&gt;Hello&lt;/p&gt;&lt;img src=&quot;https://tracker', $response->content());

        // With ?images=1 the original src survives inside the srcdoc attribute.
        $withImages = $this->actingAs($admin)->get("/messages/{$message->id}?images=1");
        $this->assertStringContainsString('https://tracker.example.com/pixel.gif', $withImages->content());
        $withImages->assertDontSee('Load remote images');
    }

    public function test_prune_command_deletes_only_old_content(): void
    {
        MessageContent::create(['ses_message_id' => 'fresh', 'html' => '<p>new</p>']);
        $old = MessageContent::create(['ses_message_id' => 'stale', 'html' => '<p>old</p>']);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->artisan('app:prune-content')->assertSuccessful();

        $this->assertSame(['fresh'], MessageContent::pluck('ses_message_id')->all());
    }

    public function test_api_token_can_be_created_and_revoked_in_settings(): void
    {
        $admin = User::factory()->withTwoFactor()->create();

        $this->actingAs($admin)
            ->post('/settings/api-tokens', ['name' => 'acme-app'])
            ->assertRedirect('/settings/api-tokens')
            ->assertSessionHas('plainToken');

        $token = ApiToken::sole();
        $this->assertSame('acme-app', $token->name);

        $this->actingAs($admin)
            ->delete("/settings/api-tokens/{$token->id}")
            ->assertRedirect('/settings/api-tokens');

        $this->assertDatabaseCount('api_tokens', 0);
    }
}
