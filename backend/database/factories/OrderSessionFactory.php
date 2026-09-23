<?php

namespace Database\Factories;

use App\Models\OrderSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderSession>
 */
class OrderSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'active',
            'step' => 'welcome',
            'context' => [],
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
