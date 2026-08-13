<?php

namespace Database\Factories;

use App\Models\PhonebookFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhonebookFolder>
 */
class PhonebookFolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
