<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large']),
            'attributes' => [],
            'sku' => strtoupper(fake()->unique()->bothify('VAR-####??')),
            'price_delta' => 0,
            'stock_tracked' => false,
            'is_active' => true,
        ];
    }
}
