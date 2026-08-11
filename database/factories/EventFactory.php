<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'type' => 'delivery',
            'occurred_at' => now(),
            'payload' => [],
        ];
    }

    public function configure(): static
    {
        // Keep topic_id consistent with the parent message unless set.
        return $this->afterMaking(function (Event $event) {
            $event->topic_id ??= Message::find($event->message_id)?->topic_id;
        });
    }
}
