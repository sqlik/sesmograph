<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withTwoFactor()->create();
    }

    public function test_guest_cannot_reach_topics(): void
    {
        $this->get('/topics')->assertRedirect('/login');
    }

    public function test_user_without_2fa_is_pushed_to_setup(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/topics')
            ->assertRedirect('/two-factor/setup');
    }

    public function test_topic_can_be_created_with_generated_token(): void
    {
        $this->actingAs($this->admin())
            ->post('/topics', ['name' => 'acme-app', 'description' => 'Main app'])
            ->assertRedirect();

        $topic = Topic::sole();
        $this->assertSame('acme-app', $topic->name);
        $this->assertSame(48, strlen($topic->webhook_token));
        $this->assertTrue($topic->active);
    }

    public function test_label_color_accepts_swatches_and_custom_hex_only(): void
    {
        $base = ['name' => 'acme-app', 'description' => null];

        $this->actingAs($this->admin())->post('/topics', $base + ['color' => 'mint']);
        $this->assertSame('mint', Topic::sole()->color);

        $topic = Topic::sole();
        $this->actingAs($this->admin())
            ->put("/topics/{$topic->id}", $base + ['color' => '#a1b2c3', 'active' => 1]);
        $this->assertSame('#a1b2c3', $topic->fresh()->color);
        $this->assertSame('#a1b2c3', $topic->fresh()->colorHex());

        $this->actingAs($this->admin())
            ->put("/topics/{$topic->id}", $base + ['color' => 'not-a-color', 'active' => 1])
            ->assertSessionHasErrors('color');
    }

    public function test_index_lists_topics(): void
    {
        Topic::factory()->create(['name' => 'billing-service']);

        $this->actingAs($this->admin())
            ->get('/topics')
            ->assertOk()
            ->assertSee('billing-service');
    }

    public function test_setup_page_contains_webhook_url_and_aws_instructions(): void
    {
        $topic = Topic::factory()->create(['name' => 'acme-app']);

        $this->actingAs($this->admin())
            ->get("/topics/{$topic->id}/setup")
            ->assertOk()
            ->assertSee($topic->webhook_token)
            ->assertSee('acme-app-ses')
            ->assertSee('Include original email headers');
    }

    public function test_show_page_without_events_points_to_aws_setup(): void
    {
        $topic = Topic::factory()->create(['name' => 'acme-app']);

        $this->actingAs($this->admin())
            ->get("/topics/{$topic->id}")
            ->assertOk()
            ->assertSee('No events yet')
            ->assertSee("/topics/{$topic->id}/setup");
    }

    public function test_topic_can_be_updated_and_deactivated(): void
    {
        $topic = Topic::factory()->create();

        $this->actingAs($this->admin())
            ->put("/topics/{$topic->id}", [
                'name' => 'renamed',
                'retention_days' => 30,
                // no "active" checkbox -> deactivate
            ])
            ->assertRedirect("/topics/{$topic->id}");

        $topic->refresh();
        $this->assertSame('renamed', $topic->name);
        $this->assertSame(30, (int) $topic->retention_days);
        $this->assertFalse($topic->active);
    }

    public function test_topic_deletion_cascades(): void
    {
        $topic = Topic::factory()->create();
        $message = $topic->messages()->create(['ses_message_id' => 'm-1']);
        $message->events()->create([
            'topic_id' => $topic->id,
            'type' => 'send',
            'occurred_at' => now(),
            'payload' => [],
        ]);

        $this->actingAs($this->admin())
            ->delete("/topics/{$topic->id}")
            ->assertRedirect('/topics');

        $this->assertDatabaseCount('topics', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_dashboard_shows_topics(): void
    {
        Topic::factory()->create(['name' => 'billing-service']);

        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('billing-service');
    }
}
