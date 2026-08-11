<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Message;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->withTwoFactor()->create();
    }

    public function test_guest_cannot_reach_messages(): void
    {
        $this->get('/messages')->assertRedirect('/login');
    }

    public function test_index_lists_messages_newest_first(): void
    {
        Message::factory()->create(['subject' => 'Older invoice', 'last_event_at' => now()->subHour()]);
        Message::factory()->create(['subject' => 'Newer welcome', 'last_event_at' => now()]);

        $response = $this->actingAs($this->admin)->get('/messages')->assertOk();

        $this->assertTrue(
            strpos($response->content(), 'Newer welcome') < strpos($response->content(), 'Older invoice'),
        );
    }

    public function test_search_matches_recipient_subject_and_message_id(): void
    {
        Message::factory()->create(['subject' => 'Password reset', 'recipients' => ['alice@example.com']]);
        Message::factory()->create(['subject' => 'Weekly digest', 'recipients' => ['bob@example.com'], 'ses_message_id' => 'ses-id-42']);

        $this->actingAs($this->admin)->get('/messages?q=alice@example.com')
            ->assertSee('Password reset')->assertDontSee('Weekly digest');

        $this->actingAs($this->admin)->get('/messages?q=digest')
            ->assertSee('Weekly digest')->assertDontSee('Password reset');

        $this->actingAs($this->admin)->get('/messages?q=ses-id-42')
            ->assertSee('Weekly digest')->assertDontSee('Password reset');
    }

    public function test_filters_by_topic_status_and_date(): void
    {
        $topicA = Topic::factory()->create(['name' => 'topic-a']);
        $topicB = Topic::factory()->create(['name' => 'topic-b']);

        Message::factory()->for($topicA)->create(['subject' => 'From A', 'status' => 'bounced']);
        Message::factory()->for($topicB)->create(['subject' => 'From B', 'status' => 'delivered', 'last_event_at' => now()->subDays(10)]);

        $this->actingAs($this->admin)->get("/messages?topic={$topicA->id}")
            ->assertSee('From A')->assertDontSee('From B');

        $this->actingAs($this->admin)->get('/messages?status=bounced')
            ->assertSee('From A')->assertDontSee('From B');

        $this->actingAs($this->admin)->get('/messages?from='.now()->subDay()->toDateString())
            ->assertSee('From A')->assertDontSee('From B');

        $this->actingAs($this->admin)->get('/messages?to='.now()->subDays(5)->toDateString())
            ->assertSee('From B')->assertDontSee('From A');
    }

    public function test_filters_by_event_type(): void
    {
        $bounced = Message::factory()->create(['subject' => 'Bounced one']);
        Event::factory()->for($bounced)->create(['topic_id' => $bounced->topic_id, 'type' => 'bounce']);

        $delivered = Message::factory()->create(['subject' => 'Delivered one']);
        Event::factory()->for($delivered)->create(['topic_id' => $delivered->topic_id, 'type' => 'delivery']);

        $this->actingAs($this->admin)->get('/messages?type=bounce')
            ->assertSee('Bounced one')->assertDontSee('Delivered one');
    }

    public function test_pagination_shows_25_per_page(): void
    {
        $topic = Topic::factory()->create();
        Message::factory()->count(26)->for($topic)->create();

        $this->actingAs($this->admin)->get('/messages')
            ->assertOk()
            ->assertSee('1-25')
            ->assertSee('of 26')
            ->assertSee('Next');
    }
}
