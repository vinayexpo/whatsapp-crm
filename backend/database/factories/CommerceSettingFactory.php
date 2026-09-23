<?php

namespace Database\Factories;

use App\Models\CommerceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommerceSetting>
 */
class CommerceSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_type_id' => null,
            'currency' => 'INR',
            'default_tax_rate_bp' => 0,
            'order_number_prefix' => 'ORD',
            'session_timeout_minutes' => 30,
            'settings' => [],
        ];
    }
}
