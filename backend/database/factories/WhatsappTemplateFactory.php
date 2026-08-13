<?php

namespace Database\Factories;

use App\Models\ApiConnection;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappTemplate>
 */
class WhatsappTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().'_'.fake()->word();

        return [
            'api_connection_id' => ApiConnection::factory(),
            'meta_template_id' => (string) fake()->numerify('##########'),
            'name' => $name,
            'language' => 'en_US',
            'category' => fake()->randomElement(['marketing', 'utility', 'authentication']),
            'status' => 'approved',
            'body' => 'Hello {{1}}, your order {{2}} is confirmed.',
            'variables' => ['1', '2'],
            'synced_at' => now(),
        ];
    }
}
