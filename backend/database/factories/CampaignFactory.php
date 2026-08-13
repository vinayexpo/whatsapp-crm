<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'channel' => fake()->randomElement(['whatsapp', 'instagram', 'both']),
            'status' => 'draft',
            'message' => fake()->sentence(),
            'audience_tag' => fake()->randomElement(['VIP', 'New', 'Enterprise', 'Wholesale']),
            'recipient_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'replied_count' => 0,
            'scheduled_at' => null,
            'dispatched_at' => null,
        ];
    }
}
