<?php

namespace App\Console\Commands;

use App\Models\ApiConnection;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('debug:campaign-send')]
class DebugCampaignSend extends Command
{
    public function handle(): void
    {
        $connection = ApiConnection::query()->where('channel', 'whatsapp')->first();

        $response = Http::withToken($connection->access_token)->post(
            "https://graph.facebook.com/v20.0/{$connection->phone_number_id}/messages",
            [
                'messaging_product' => 'whatsapp',
                'to' => 'whatsapp',
                'text' => ['body' => 'test'],
            ]
        );

        $this->info('Status: '.$response->status());
        $this->info('Body: '.$response->body());
    }
}
