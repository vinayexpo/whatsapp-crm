<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        return ActivityLogResource::collection(
            ActivityLog::query()->latest('occurred_at')->paginate($this->perPageFrom($request, default: 10, max: 50))
        );
    }
}
