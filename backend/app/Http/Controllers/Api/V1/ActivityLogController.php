<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ActivityLogResource::collection(
            ActivityLog::query()->latest('occurred_at')->limit(50)->get()
        );
    }
}
