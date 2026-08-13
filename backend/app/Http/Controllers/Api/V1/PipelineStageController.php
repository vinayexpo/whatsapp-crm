<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PipelineStageResource;
use App\Models\PipelineStage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PipelineStageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PipelineStageResource::collection(
            PipelineStage::query()->orderBy('position')->get()
        );
    }
}
