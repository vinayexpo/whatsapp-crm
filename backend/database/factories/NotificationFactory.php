<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement([
                'new_message', 'campaign_completed', 'automation_triggered',
                'whatsapp_call_missed', 'whatsapp_call_completed',
                'template_approved', 'template_rejected',
            ]),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'data' => [],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
