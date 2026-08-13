<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyMetricResource;
use App\Models\DailyMetric;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DailyMetricController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DailyMetric::class);

        return DailyMetricResource::collection(
            DailyMetric::query()->orderBy('date')->get()
        );
    }
}
