<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Inventory::class);

        $user = $request->user();

        return InventoryResource::collection(
            Inventory::query()
                ->with(['branch', 'product', 'productVariant'])
                ->when($request->filled('branchId'), function ($q) use ($request) {
                    $branch = Branch::where('uuid', $request->query('branchId'))->first();
                    $q->where('branch_id', $branch?->id);
                })
                ->when($request->filled('productId'), function ($q) use ($request) {
                    $product = Product::where('uuid', $request->query('productId'))->first();
                    $q->where('product_id', $product?->id);
                })
                ->when($user->hasAnyRole(['branch_manager', 'staff']), fn ($q) => $q->where('branch_id', $user->staff_branch_id))
                ->paginate($this->perPageFrom($request))
        );
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorize('update', $inventory);

        $data = $request->validate([
            'stockQuantity' => ['sometimes', 'integer'],
            'lowStockThreshold' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'trackStock' => ['sometimes', 'boolean'],
        ]);

        $map = [
            'stockQuantity' => 'stock_quantity',
            'lowStockThreshold' => 'low_stock_threshold',
            'trackStock' => 'track_stock',
        ];

        $update = [];
        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        $inventory->update($update);

        return response()->json(['data' => new InventoryResource($inventory->load(['branch', 'product', 'productVariant']))]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branchId' => ['required', 'string', 'exists:branches,uuid'],
            'productId' => ['required', 'string', 'exists:products,uuid'],
            'productVariantId' => ['sometimes', 'nullable', 'string', 'exists:product_variants,uuid'],
            'stockQuantity' => ['sometimes', 'integer'],
            'lowStockThreshold' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'trackStock' => ['sometimes', 'boolean'],
        ]);

        $branch = Branch::where('uuid', $data['branchId'])->firstOrFail();
        $product = Product::where('uuid', $data['productId'])->firstOrFail();
        $variant = ! empty($data['productVariantId'])
            ? ProductVariant::where('uuid', $data['productVariantId'])->firstOrFail()
            : null;

        $this->authorize('create', Inventory::class);

        $inventory = Inventory::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
            ],
            [
                'stock_quantity' => $data['stockQuantity'] ?? 0,
                'low_stock_threshold' => $data['lowStockThreshold'] ?? null,
                'track_stock' => $data['trackStock'] ?? false,
            ]
        );

        return response()->json(['data' => new InventoryResource($inventory->load(['branch', 'product', 'productVariant']))], 201);
    }
}
