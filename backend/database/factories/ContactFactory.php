<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channel = fake()->randomElement(['whatsapp', 'instagram']);

        return [
            'name' => fake()->name(),
            'avatar_url' => null,
            'channel' => $channel,
            'handle' => $channel === 'whatsapp' ? fake()->e164PhoneNumber() : '@'.fake()->userName(),
            'phone' => $channel === 'whatsapp' ? fake()->e164PhoneNumber() : null,
            'email' => fake()->safeEmail(),
            'location' => fake()->city(),
            'pipeline_stage_id' => PipelineStage::query()->inRandomOrder()->value('id') ?? 'new-lead',
            'deal_value' => fake()->numberBetween(0, 5000),
            'last_interaction_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'notes' => [],
            'purchases' => [],
        ];
    }
}
