<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['message', 'campaign', 'pipeline', 'contact']),
            'description' => fake()->sentence(),
            'channel' => fake()->randomElement(['whatsapp', 'instagram']),
            'occurred_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
