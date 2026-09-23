<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Zone',
            'type' => 'radius',
            'radius_km' => fake()->randomFloat(2, 1, 10),
            'polygon' => null,
            'delivery_charge' => fake()->numberBetween(2000, 6000),
            'free_delivery_threshold' => null,
            'min_order_amount' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
