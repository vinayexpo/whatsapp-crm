<?php

namespace Database\Factories;

use App\Models\ApiConnection;
use App\Models\WhatsappFlow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappFlow>
 */
class WhatsappFlowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_connection_id' => ApiConnection::factory(),
            'meta_flow_id' => (string) fake()->numerify('##########'),
            'name' => fake()->unique()->word().'_flow',
            'status' => 'published',
            'categories' => ['APPOINTMENT_BOOKING'],
            'synced_at' => now(),
        ];
    }
}
