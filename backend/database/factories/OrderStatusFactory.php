<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatus>
 */
class OrderStatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'business_type_id' => null,
            'slug' => $this->faker->unique()->word(),
            'label' => $this->faker->words(2, true),
            'sort_order' => 0,
            'is_terminal' => false,
            'is_cancellable_from' => true,
            'notify_customer' => false,
            'notification_template_id' => null,
            'allowed_next_status_ids' => [],
        ];
    }
}
