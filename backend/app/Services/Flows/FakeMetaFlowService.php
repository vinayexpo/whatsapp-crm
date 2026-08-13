<?php

namespace App\Services\Flows;

use App\Models\ApiConnection;
use Illuminate\Support\Facades\Log;

class FakeMetaFlowService implements FlowSyncServiceInterface
{
    public function fetchFlows(ApiConnection $connection): array
    {
        Log::info('FakeMetaFlowService: simulated flow sync', [
            'api_connection_id' => $connection->id,
        ]);

        return [
            [
                'meta_flow_id' => '2001_'.$connection->id,
                'name' => 'appointment_booking',
                'status' => 'published',
                'categories' => ['APPOINTMENT_BOOKING'],
            ],
            [
                'meta_flow_id' => '2002_'.$connection->id,
                'name' => 'customer_feedback_survey',
                'status' => 'published',
                'categories' => ['SURVEY'],
            ],
            [
                'meta_flow_id' => '2003_'.$connection->id,
                'name' => 'lead_generation',
                'status' => 'draft',
                'categories' => ['LEAD_GENERATION'],
            ],
        ];
    }
}
