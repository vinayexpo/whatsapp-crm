<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\OrderStatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    use PaginatesRequests;

    private const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'completed', 'cancelled'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Order::class);

        $user = $request->user();

        return OrderResource::collection(
            Order::query()
                ->with(['branch', 'contact', 'assignedStaff'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('uuid', $request->query('branch_id'))))
                ->when($user->hasRole('branch_manager'), fn ($q) => $q->where('branch_id', $user->staff_branch_id))
                ->when($user->hasRole('staff'), fn ($q) => $q->where('assigned_staff_user_id', $user->id))
                ->orderByDesc('created_at')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json(['data' => new OrderResource($order->load(['branch', 'contact', 'assignedStaff', 'items.product', 'items.productVariant']))]);
    }

    public function updateStatus(Request $request, Order $order, OrderStatusTransitionService $transitions): JsonResponse
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::STATUSES)],
        ]);

        $newStatus = $transitions->resolveBySlug($order->company_id, $data['status']);

        if ($newStatus) {
            $transitions->transition($order, $newStatus);
        } else {
            $order->update(['status' => $data['status']]);
        }

        return response()->json(['data' => new OrderResource($order->fresh()->load(['branch', 'contact', 'assignedStaff']))]);
    }

    public function assign(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'staffUserId' => ['nullable', 'string', 'exists:users,uuid'],
        ]);

        $userId = ! empty($data['staffUserId'])
            ? User::where('uuid', $data['staffUserId'])->value('id')
            : null;

        $order->update(['assigned_staff_user_id' => $userId]);

        return response()->json(['data' => new OrderResource($order->load(['branch', 'contact', 'assignedStaff']))]);
    }
}
