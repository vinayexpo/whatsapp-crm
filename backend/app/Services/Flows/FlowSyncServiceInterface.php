<?php

namespace App\Services\Flows;

use App\Models\ApiConnection;

interface FlowSyncServiceInterface
{
    /**
     * Fetch the current set of WhatsApp Flows for the given connection's
     * WhatsApp Business Account from Meta and return them as plain arrays
     * ready to be upserted into whatsapp_flows.
     *
     * @return array<int, array{meta_flow_id: string, name: string, status: string, categories: array<int, string>}>
     */
    public function fetchFlows(ApiConnection $connection): array;
}
