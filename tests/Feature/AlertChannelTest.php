<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertChannelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['totp_confirmed_at' => now()]);
    }

    public function test_telegram_channel_can_be_created_with_encrypted_config(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/alerts/channels', [
                'name' => 'Ops Telegram',
                'type' => 'telegram',
                'config' => ['bot_token' => '123:secret', 'chat_id' => '-10042'],
            ])
            ->assertRedirect('/settings/alerts');

        $channel = AlertChannel::sole();

        $this->assertSame('telegram', $channel->type);
        $this->assertSame('123:secret', $channel->config['bot_token']);
        // The raw column must not leak the token.
        $this->assertStringNotContainsString('123:secret', $channel->getRawOriginal('config'));
    }

    public function test_pushover_channel_can_be_created(): void
    {
        $this->actingAs($this->admin())
            ->post('/settings/alerts/channels', [
                'name' => 'Phone',
                'type' => 'pushover',
                'config' => ['app_token' => 'azGDORePK8gMaC0QOYAMyEEuzJnyUi', 'user_key' => 'uQiRzpo4DXghDmr9QzzfQu27cmVRsG'],
            ])
            ->assertRedirect('/settings/alerts');

        $this->assertSame('pushover', AlertChannel::sole()->type);
    }

    public function test_blank_secret_on_update_keeps_the_stored_value(): void
    {
        $channel = AlertChannel::create([
            'name' => 'Ops Telegram',
            'type' => 'telegram',
            'config' => ['bot_token' => '123:secret', 'chat_id' => '-10042'],
        ]);

        $this->actingAs($this->admin())
            ->put("/settings/alerts/channels/{$channel->id}", [
                'name' => 'Ops Telegram',
                'config' => ['bot_token' => '', 'chat_id' => '-100777'],
                'enabled' => '1',
            ])
            ->assertRedirect('/settings/alerts');

        $channel->refresh();

        $this->assertSame('123:secret', $channel->config['bot_token']);
        $this->assertSame('-100777', $channel->config['chat_id']);
    }

    public function test_send_test_hits_pushover_api(): void
    {
        Http::fake(['api.pushover.net/*' => Http::response(['status' => 1])]);

        $channel = AlertChannel::create([
            'name' => 'Phone',
            'type' => 'pushover',
            'config' => ['app_token' => 'app-token', 'user_key' => 'user-key'],
        ]);

        $this->actingAs($this->admin())
            ->post("/settings/alerts/channels/{$channel->id}/test")
            ->assertRedirect('/settings/alerts')
            ->assertSessionHas('status', 'Test sent through "Phone"');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.pushover.net')
                && $request['token'] === 'app-token'
                && $request['user'] === 'user-key'
                && $request['title'] === 'Test alert from sesmograph';
        });
    }

    public function test_failed_test_reports_the_error(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);

        $channel = AlertChannel::create([
            'name' => 'Ops Telegram',
            'type' => 'telegram',
            'config' => ['bot_token' => 'bad', 'chat_id' => '-10042'],
        ]);

        $this->actingAs($this->admin())
            ->post("/settings/alerts/channels/{$channel->id}/test")
            ->assertRedirect('/settings/alerts');

        $this->assertStringContainsString('Test failed', session('status'));
    }

    public function test_webhook_test_signs_the_payload(): void
    {
        Http::fake(['n8n.example.com/*' => Http::response(['ok' => true])]);

        $channel = AlertChannel::create([
            'name' => 'n8n',
            'type' => 'webhook',
            'config' => ['url' => 'https://n8n.example.com/webhook/abc', 'secret' => 'hush'],
        ]);

        $this->actingAs($this->admin())
            ->post("/settings/alerts/channels/{$channel->id}/test")
            ->assertRedirect('/settings/alerts');

        Http::assertSent(function ($request) {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'hush');

            return $request->hasHeader('X-Sesmograph-Signature', $expected)
                && json_decode($request->body(), true)['event'] === 'alert';
        });
    }
}
