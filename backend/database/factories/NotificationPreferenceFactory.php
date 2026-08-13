<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
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
            'new_message_alerts' => true,
            'campaign_completed' => true,
            'automation_triggered' => false,
            'daily_summary_email' => true,
            'weekly_analytics_report' => true,
            'sound_alerts' => false,
        ];
    }
}
