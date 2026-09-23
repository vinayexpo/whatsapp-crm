<?php

namespace Database\Factories;

use App\Models\AddonDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonDefinition>
 */
class AddonDefinitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->numberBetween(1000, 10000),
            'max_quantity' => 1,
            'is_active' => true,
        ];
    }
}
