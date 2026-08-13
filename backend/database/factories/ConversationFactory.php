<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'channel' => fake()->randomElement(['whatsapp', 'instagram']),
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
