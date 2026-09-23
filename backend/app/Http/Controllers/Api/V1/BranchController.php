<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\ApiConnection;
use App\Models\Branch;
use App\Models\BranchPrice;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        $user = $request->user();

        return BranchResource::collection(
            Branch::query()
                ->with(['apiConnection', 'businessType'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
                ->when($user->hasAnyRole(['branch_manager', 'staff']), fn ($q) => $q->where('id', $user->staff_branch_id))
                ->orderBy('name')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $data = $this->validateBranch($request);

        $attributes = $this->mapBranchData($data);
        $attributes['status'] ??= 'active';
        $attributes['timezone'] ??= 'Asia/Kolkata';
        $attributes['currency'] ??= 'INR';
        $attributes['is_default'] ??= false;

        $branch = Branch::query()->create($attributes);

        return response()->json(['data' => new BranchResource($branch->load(['apiConnection', 'businessType']))], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return response()->json(['data' => new BranchResource($branch->load(['apiConnection', 'businessType']))]);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $data = $this->validateBranch($request, isUpdate: true);

        $branch->update($this->mapBranchData($data, partial: true));

        return response()->json(['data' => new BranchResource($branch->load(['apiConnection', 'businessType']))]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }

    public function setProductAvailability(Request $request, Branch $branch, Product $product): JsonResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'isAvailable' => ['required', 'boolean'],
        ]);

        $branchProduct = BranchProduct::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'product_id' => $product->id],
            ['is_available' => $data['isAvailable']]
        );

        return response()->json(['data' => [
            'branchId' => $branch->uuid,
            'productId' => $product->uuid,
            'isAvailable' => $branchProduct->is_available,
        ]]);
    }

    public function setProductPrice(Request $request, Branch $branch, Product $product): JsonResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'productVariantId' => ['sometimes', 'nullable', 'string', 'exists:product_variants,uuid'],
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $variant = ! empty($data['productVariantId'])
            ? ProductVariant::where('uuid', $data['productVariantId'])->firstOrFail()
            : null;

        $branchPrice = BranchPrice::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id],
            ['price' => $data['price']]
        );

        return response()->json(['data' => [
            'branchId' => $branch->uuid,
            'productId' => $product->uuid,
            'productVariantId' => $variant?->uuid,
            'price' => $branchPrice->price,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBranch(Request $request, bool $isUpdate = false): array
    {
        $sometimes = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'apiConnectionId' => ['sometimes', 'nullable', 'string', 'exists:api_connections,uuid'],
            'businessTypeId' => ['sometimes', 'nullable', 'integer', 'exists:business_types,id'],
            'name' => [$sometimes, 'string', 'max:255'],
            'slug' => [$sometimes, 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'in:active,inactive'],
            'operatingHours' => ['sometimes', 'nullable', 'array'],
            'holidays' => ['sometimes', 'nullable', 'array'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'minOrderAmount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'defaultDeliveryCharge' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'deliveryRadiusKm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapBranchData(array $data, bool $partial = false): array
    {
        $update = [];

        if (array_key_exists('apiConnectionId', $data)) {
            $update['api_connection_id'] = $data['apiConnectionId']
                ? ApiConnection::where('uuid', $data['apiConnectionId'])->value('id')
                : null;
        }

        if (array_key_exists('businessTypeId', $data)) {
            $update['business_type_id'] = $data['businessTypeId'];
        }

        $map = [
            'name' => 'name',
            'slug' => 'slug',
            'address' => 'address',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'phone' => 'phone',
            'status' => 'status',
            'operatingHours' => 'operating_hours',
            'holidays' => 'holidays',
            'timezone' => 'timezone',
            'minOrderAmount' => 'min_order_amount',
            'defaultDeliveryCharge' => 'default_delivery_charge',
            'deliveryRadiusKm' => 'delivery_radius_km',
            'currency' => 'currency',
            'isDefault' => 'is_default',
        ];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        return $update;
    }
}
