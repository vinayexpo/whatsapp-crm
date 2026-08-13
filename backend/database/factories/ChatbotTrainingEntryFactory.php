<?php

namespace Database\Factories;

use App\Models\Chatbot;
use App\Models\ChatbotTrainingEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatbotTrainingEntry>
 */
class ChatbotTrainingEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chatbot_id' => Chatbot::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'source' => 'manual',
        ];
    }
}
