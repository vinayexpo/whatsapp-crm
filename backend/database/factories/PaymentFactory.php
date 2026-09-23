<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'method' => 'cod',
            'amount' => fake()->numberBetween(10000, 50000),
            'status' => 'pending',
            'provider_reference' => null,
            'failure_reason' => null,
            'paid_at' => null,
            'raw_payload' => null,
        ];
    }
}
