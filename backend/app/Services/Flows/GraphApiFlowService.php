<?php

namespace App\Services\Flows;

use App\Models\ApiConnection;
use Illuminate\Support\Facades\Http;

class GraphApiFlowService implements FlowSyncServiceInterface
{
    public function fetchFlows(ApiConnection $connection): array
    {
        $response = Http::withToken($connection->access_token)
            ->get("https://graph.facebook.com/v20.0/{$connection->waba_id}/flows", [
                'fields' => 'id,name,status,categories',
                'limit' => 100,
            ])
            ->throw();

        $flows = [];

        foreach ($response->json('data', []) as $flow) {
            $flows[] = [
                'meta_flow_id' => (string) $flow['id'],
                'name' => $flow['name'],
                'status' => strtolower($flow['status']),
                'categories' => $flow['categories'] ?? [],
            ];
        }

        return $flows;
    }
}
