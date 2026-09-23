<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(5000, 20000);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'name_snapshot' => fake()->words(3, true),
            'unit_price_snapshot' => $unitPrice,
            'quantity' => $quantity,
            'selected_options' => [],
            'line_total' => $unitPrice * $quantity,
        ];
    }
}
