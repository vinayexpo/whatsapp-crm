<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderSessionResource;
use App\Models\OrderSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderSessionController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrderSession::class);

        return OrderSessionResource::collection(
            OrderSession::query()
                ->with(['conversation', 'contact', 'branch', 'order'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->orderByDesc('last_interaction_at')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function show(OrderSession $orderSession): JsonResponse
    {
        $this->authorize('view', $orderSession);

        return response()->json(['data' => new OrderSessionResource($orderSession->load(['conversation', 'contact', 'branch', 'order']))]);
    }
}
