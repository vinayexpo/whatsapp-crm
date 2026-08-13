<?php

namespace Database\Factories;

use App\Models\DailyMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyMetric>
 */
class DailyMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->date(),
            'whatsapp_sent' => fake()->numberBetween(0, 200),
            'instagram_sent' => fake()->numberBetween(0, 200),
            'delivered' => fake()->numberBetween(0, 200),
            'read' => fake()->numberBetween(0, 200),
            'replied' => fake()->numberBetween(0, 100),
            'avg_response_minutes' => fake()->numberBetween(1, 120),
        ];
    }
}
