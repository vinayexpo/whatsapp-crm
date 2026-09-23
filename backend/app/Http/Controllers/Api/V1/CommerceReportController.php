<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommerceReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $this->authorize('commerce-reports.view');

        $user = $request->user();

        $data = $request->validate([
            'branchId' => ['sometimes', 'nullable', 'string', 'exists:branches,uuid'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'groupBy' => ['sometimes', 'in:day,week,month'],
        ]);

        $groupBy = $data['groupBy'] ?? 'day';
        $dateExpr = match ($groupBy) {
            'week' => "strftime('%Y-W%W', placed_at)",
            'month' => "strftime('%Y-%m', placed_at)",
            default => "strftime('%Y-%m-%d', placed_at)",
        };

        $query = Order::query()
            ->whereNotNull('placed_at')
            ->when($data['branchId'] ?? null, fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('uuid', $data['branchId'])))
            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('placed_at', '>=', $data['from']))
            ->when($data['to'] ?? null, fn ($q) => $q->whereDate('placed_at', '<=', $data['to']))
            ->when($user->hasRole('branch_manager'), fn ($q) => $q->where('branch_id', $user->staff_branch_id));

        $rows = $query
            ->selectRaw("{$dateExpr} as period, count(*) as order_count, coalesce(sum(grand_total), 0) as revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'period' => $row->period,
                'orderCount' => (int) $row->order_count,
                'revenue' => (int) $row->revenue,
            ]),
        ]);
    }

    public function salesByBranch(Request $request): JsonResponse
    {
        $this->authorize('commerce-reports.view');

        $user = $request->user();

        $data = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
        ]);

        $rows = Order::query()
            ->whereNotNull('placed_at')
            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('placed_at', '>=', $data['from']))
            ->when($data['to'] ?? null, fn ($q) => $q->whereDate('placed_at', '<=', $data['to']))
            ->when($user->hasRole('branch_manager'), fn ($q) => $q->where('branch_id', $user->staff_branch_id))
            ->selectRaw('branch_id, count(*) as order_count, coalesce(sum(grand_total), 0) as revenue')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $branches = Branch::query()->whereIn('id', $rows->keys())->get()->keyBy('id');

        return response()->json([
            'data' => $rows->map(fn ($row, $branchId) => [
                'branchId' => $branches->get($branchId)?->uuid,
                'branchName' => $branches->get($branchId)?->name,
                'orderCount' => (int) $row->order_count,
                'revenue' => (int) $row->revenue,
            ])->values(),
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $this->authorize('commerce-reports.view');

        $user = $request->user();

        $data = $request->validate([
            'branchId' => ['sometimes', 'nullable', 'string', 'exists:branches,uuid'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = $data['limit'] ?? 10;

        $branchId = ! empty($data['branchId'])
            ? Branch::query()->where('uuid', $data['branchId'])->value('id')
            : null;

        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.placed_at')
            ->when($branchId, fn ($q) => $q->where('orders.branch_id', $branchId))
            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('orders.placed_at', '>=', $data['from']))
            ->when($data['to'] ?? null, fn ($q) => $q->whereDate('orders.placed_at', '<=', $data['to']))
            ->when($user->hasRole('branch_manager'), fn ($q) => $q->where('orders.branch_id', $user->staff_branch_id))
            ->select('order_items.product_id', 'order_items.name_snapshot')
            ->selectRaw('sum(order_items.quantity) as total_quantity, coalesce(sum(order_items.line_total), 0) as total_revenue')
            ->groupBy('order_items.product_id', 'order_items.name_snapshot')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        $products = Product::query()->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'productId' => $products->get($row->product_id)?->uuid,
                'name' => $row->name_snapshot,
                'totalQuantity' => (int) $row->total_quantity,
                'totalRevenue' => (int) $row->total_revenue,
            ]),
        ]);
    }
}
