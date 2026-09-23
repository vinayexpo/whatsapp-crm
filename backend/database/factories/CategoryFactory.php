<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'parent_id' => null,
            'name' => ucfirst($name),
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
