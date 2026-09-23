<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10000, 50000);

        return [
            'order_number' => 'ORD-'.fake()->unique()->numberBetween(100000, 999999),
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'delivery_address' => fake()->address(),
            'subtotal' => $subtotal,
            'tax_total' => 0,
            'delivery_charge' => 0,
            'discount_total' => 0,
            'grand_total' => $subtotal,
            'currency' => 'INR',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'placed_at' => now(),
        ];
    }
}
