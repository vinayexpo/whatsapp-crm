<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function pipelineFunnel(): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $counts = Contact::query()
            ->whereNotNull('pipeline_stage_id')
            ->selectRaw('pipeline_stage_id as stage, count(*) as count')
            ->groupBy('pipeline_stage_id')
            ->pluck('count', 'stage');

        return response()->json([
            'data' => $counts->map(fn ($count, $stage) => [
                'stage' => $stage,
                'count' => $count,
            ])->values(),
        ]);
    }
}
