<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\WhatsappCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappCall>
 */
class WhatsappCallFactory extends Factory
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
            'status' => 'ringing',
            'meta_call_id' => null,
            'transcript' => null,
            'collected_variables' => null,
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
                ['role' => 'agent', 'text' => "What's your budget range?", 'at' => now()->subMinutes(4)->toIso8601String()],
                ['role' => 'lead', 'text' => 'Around $5,000 to $10,000.', 'at' => now()->subMinutes(3)->toIso8601String()],
            ],
            'collected_variables' => ['budget' => '$5,000 to $10,000'],
        ]);
    }
}
