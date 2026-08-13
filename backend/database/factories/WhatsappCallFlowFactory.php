<?php

namespace Database\Factories;

use App\Models\ApiConnection;
use App\Models\WhatsappCallFlow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappCallFlow>
 */
class WhatsappCallFlowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_connection_id' => ApiConnection::factory()->state(['channel' => 'whatsapp']),
            'name' => fake()->words(2, true).' Call Flow',
            'status' => 'paused',
            'greeting_message' => "Hi, thanks for calling. How can we help you today?",
            'nodes' => [
                ['id' => 'q1', 'type' => 'question', 'prompt' => "What's your budget range?", 'variable_key' => 'budget', 'input_type' => 'speech'],
                ['id' => 'q2', 'type' => 'question', 'prompt' => 'When are you looking to get started?', 'variable_key' => 'timeline', 'input_type' => 'speech'],
                ['id' => 'end', 'type' => 'end_call', 'prompt' => 'Thanks, someone from our team will follow up soon.'],
            ],
            'fallback_message' => "Sorry, I didn't catch that. Let me connect you with a team member.",
            'max_retries' => 2,
        ];
    }
}
