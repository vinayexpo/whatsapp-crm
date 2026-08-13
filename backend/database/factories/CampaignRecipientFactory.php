<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'contact_id' => Contact::factory(),
            'message_id' => null,
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending',
            'failure_reason' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'replied_at' => null,
        ];
    }
}
