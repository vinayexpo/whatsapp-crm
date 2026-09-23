<?php

namespace Database\Factories;

use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessType>
 */
class BusinessTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->word();

        return [
            'slug' => $slug,
            'name' => ucfirst($slug),
            'catalog_labels' => ['category' => 'Category', 'product' => 'Product', 'variant' => 'Variant'],
            'default_order_statuses' => [
                ['slug' => 'pending', 'label' => 'Pending', 'is_terminal' => false],
                ['slug' => 'delivered', 'label' => 'Delivered', 'is_terminal' => true],
            ],
            'is_active' => true,
        ];
    }
}
