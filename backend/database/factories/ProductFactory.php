<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => null,
            'brand' => fake()->company(),
            'name' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'description' => fake()->sentence(),
            'images' => [],
            'base_price' => fake()->numberBetween(10000, 500000),
            'sale_price' => null,
            'tax_rate_bp' => 0,
            'attributes' => [],
            'delivery_available' => true,
            'pickup_available' => true,
            'is_service' => false,
            'is_active' => true,
        ];
    }
}
