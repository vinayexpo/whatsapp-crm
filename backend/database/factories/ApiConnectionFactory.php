<?php

namespace Database\Factories;

use App\Models\ApiConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiConnection>
 */
class ApiConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channel = fake()->randomElement(['whatsapp', 'instagram']);

        return [
            'channel' => $channel,
            'label' => $channel === 'whatsapp' ? 'WhatsApp Business API' : 'Instagram Business API',
            'account_name' => fake()->company(),
            'identifier' => $channel === 'whatsapp' ? fake()->phoneNumber() : '@'.fake()->userName(),
            'status' => 'disconnected',
            'access_token' => null,
            'connected_at' => null,
        ];
    }

    public function connected(): static
    {
        return $this->state(fn () => [
            'status' => 'connected',
            'access_token' => fake()->uuid(),
            'connected_at' => now(),
        ]);
    }
}
