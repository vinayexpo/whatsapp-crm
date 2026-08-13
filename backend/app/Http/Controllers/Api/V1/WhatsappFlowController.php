<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsappFlowResource;
use App\Models\ApiConnection;
use App\Models\WhatsappFlow;
use App\Services\Flows\FlowDriverResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class WhatsappFlowController extends Controller
{
    public function index(Request $request, ApiConnection $apiConnection): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WhatsappFlow::class);

        return WhatsappFlowResource::collection(
            $apiConnection->flows()->orderBy('name')->get()
        );
    }

    public function all(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WhatsappFlow::class);

        return WhatsappFlowResource::collection(
            WhatsappFlow::query()->where('status', 'published')->orderBy('name')->get()
        );
    }

    public function sync(Request $request, ApiConnection $apiConnection, FlowDriverResolver $resolver): JsonResponse
    {
        $this->authorize('sync', WhatsappFlow::class);

        if ($apiConnection->channel !== 'whatsapp') {
            throw ValidationException::withMessages([
                'apiConnection' => 'Flow sync is only available for WhatsApp connections.',
            ]);
        }

        $flows = $resolver->forConnection($apiConnection)->fetchFlows($apiConnection);
        $syncedAt = now();

        foreach ($flows as $flow) {
            WhatsappFlow::query()->updateOrCreate(
                [
                    'api_connection_id' => $apiConnection->id,
                    'meta_flow_id' => $flow['meta_flow_id'],
                ],
                [
                    'name' => $flow['name'],
                    'status' => $flow['status'],
                    'categories' => $flow['categories'],
                    'synced_at' => $syncedAt,
                ]
            );
        }

        return WhatsappFlowResource::collection(
            $apiConnection->flows()->orderBy('name')->get()
        )->response();
    }
}
