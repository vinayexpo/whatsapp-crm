<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\VoiceCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceCall>
 */
class VoiceCallFactory extends Factory
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
            'direction' => fake()->randomElement(['outbound', 'inbound']),
            'medium' => 'telephony',
            'status' => 'queued',
            'provider_call_sid' => null,
            'recording_url' => null,
            'transcript' => null,
            'qualification_fields' => null,
            'qualification_outcome' => null,
            'qualification_summary' => null,
            'needs_human_followup' => false,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
            'transcript' => [
                ['role' => 'agent', 'text' => "Hi, what's your budget range?", 'at' => now()->subMinutes(4)->toIso8601String()],
                ['role' => 'lead', 'text' => 'Around $5,000 to $10,000.', 'at' => now()->subMinutes(3)->toIso8601String()],
            ],
            'qualification_fields' => ['budget' => '$5,000 to $10,000'],
            'qualification_outcome' => 'qualified',
            'qualification_summary' => 'Lead has a clear budget and is ready to move forward.',
        ]);
    }
}
