<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'direction' => fake()->randomElement(['inbound', 'outbound']),
            'text' => fake()->sentence(),
            'status' => 'sent',
            'sent_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
