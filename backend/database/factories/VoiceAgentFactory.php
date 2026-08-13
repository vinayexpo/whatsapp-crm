<?php

namespace Database\Factories;

use App\Models\VoiceAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceAgent>
 */
class VoiceAgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Voice Agent',
            'status' => 'paused',
            'voice_mode' => 'tts',
            'tts_voice_id' => null,
            'greeting_audio_url' => null,
            'qualification_criteria' => [
                ['key' => 'budget', 'label' => 'Budget', 'question' => "What's your budget range?", 'type' => 'text'],
                ['key' => 'timeline', 'label' => 'Timeline', 'question' => 'When are you looking to get started?', 'type' => 'text'],
            ],
            'qualification_pass_criteria' => 'Lead has a defined budget and a timeline within 3 months.',
            'max_call_attempts' => 1,
            'system_prompt' => 'You are a friendly sales qualification agent calling on behalf of the company.',
        ];
    }
}
