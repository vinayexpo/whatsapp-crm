<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchPrice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchPrice>
 */
class BranchPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'price' => fake()->numberBetween(10000, 500000),
        ];
    }
}
