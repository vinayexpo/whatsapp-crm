<?php

namespace Database\Factories;

use App\Models\AutomationFlow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationFlow>
 */
class AutomationFlowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'status' => 'draft',
            'trigger' => ['type' => 'new-message', 'channel' => 'whatsapp'],
            'conditions' => [],
            'actions' => [],
            'triggered_count' => 0,
            'last_triggered_at' => null,
        ];
    }
}
