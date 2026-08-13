<?php

namespace Database\Factories;

use App\Models\Chatbot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Chatbot>
 */
class ChatbotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Bot',
            'status' => 'active',
            'widget_key' => Str::random(32),
            'allowed_origins' => null,
            'welcome_message' => fake()->sentence(),
            'general_fallback_enabled' => false,
        ];
    }
}
