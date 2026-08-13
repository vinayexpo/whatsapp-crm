<?php

namespace App\Services\Templates;

use App\Models\ApiConnection;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Log;

class FakeMetaTemplateService implements TemplateSyncServiceInterface
{
    public function fetchTemplates(ApiConnection $connection): array
    {
        Log::info('FakeMetaTemplateService: simulated template sync', [
            'api_connection_id' => $connection->id,
        ]);

        return [
            [
                'meta_template_id' => '1001_'.$connection->id,
                'name' => 'order_confirmation',
                'language' => 'en_US',
                'category' => 'utility',
                'status' => 'approved',
                'body' => 'Hi {{1}}, your order {{2}} has been confirmed and will arrive by {{3}}.',
                'variables' => ['1', '2', '3'],
            ],
            [
                'meta_template_id' => '1002_'.$connection->id,
                'name' => 'appointment_reminder',
                'language' => 'en_US',
                'category' => 'utility',
                'status' => 'approved',
                'body' => 'Hi {{1}}, this is a reminder for your appointment on {{2}}.',
                'variables' => ['1', '2'],
            ],
            [
                'meta_template_id' => '1003_'.$connection->id,
                'name' => 'seasonal_promo',
                'language' => 'en_US',
                'category' => 'marketing',
                'status' => 'approved',
                'body' => 'Hi {{1}}, enjoy {{2}}% off your next purchase with code {{3}}.',
                'variables' => ['1', '2', '3'],
            ],
        ];
    }

    public function submitTemplate(ApiConnection $connection, WhatsappTemplate $template): array
    {
        Log::info('FakeMetaTemplateService: simulated template submission', [
            'api_connection_id' => $connection->id,
            'template_id' => $template->id,
        ]);

        $status = str_contains(strtolower($template->name), 'reject') ? 'rejected' : 'approved';

        return [
            'meta_template_id' => 'fake_'.$template->id.'_'.now()->timestamp,
            'status' => $status,
        ];
    }
}
