<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'api_connection_id' => null,
            'business_type_id' => null,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_default' => false,
        ];
    }
}
