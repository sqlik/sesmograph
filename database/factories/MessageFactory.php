<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'ses_message_id' => fake()->unique()->uuid(),
            'subject' => fake()->sentence(4),
            'from_email' => 'no-reply@example.com',
            'recipients' => [fake()->safeEmail()],
            'status' => 'delivered',
            'status_at' => now(),
            'last_event_at' => now(),
        ];
    }
}
