<?php

namespace Database\Seeders;

use App\Models\ApiConnection;
use Illuminate\Database\Seeder;

class ApiConnectionsSeeder extends Seeder
{
    public function __construct(private readonly ?int $companyId = null) {}

    private const CONNECTIONS = [
        [
            'channel' => 'whatsapp',
            'label' => 'WhatsApp Business API',
            'account_name' => 'Not connected',
            'identifier' => 'Not connected',
            'status' => 'disconnected',
        ],
        [
            'channel' => 'instagram',
            'label' => 'Instagram Business API',
            'account_name' => 'Not connected',
            'identifier' => 'Not connected',
            'status' => 'disconnected',
        ],
        [
            'channel' => 'voice',
            'label' => 'Twilio Voice',
            'account_name' => 'Not connected',
            'identifier' => 'Not connected',
            'status' => 'disconnected',
        ],
    ];

    public function run(): void
    {
        foreach (self::CONNECTIONS as $connection) {
            ApiConnection::withoutGlobalScopes()->firstOrCreate(
                ['channel' => $connection['channel'], 'company_id' => $this->companyId],
                [...$connection],
            );
        }
    }
}
