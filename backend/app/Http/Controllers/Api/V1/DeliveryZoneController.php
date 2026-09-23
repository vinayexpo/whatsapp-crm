<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\Branch;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryZoneController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliveryZone::class);

        return DeliveryZoneResource::collection(
            DeliveryZone::query()
                ->with('branch')
                ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('uuid', $request->query('branch_id'))))
                ->orderBy('sort_order')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DeliveryZone::class);

        $data = $this->validateZone($request);

        $branch = Branch::where('uuid', $data['branchId'])->firstOrFail();

        $attributes = $this->mapZoneData($data);
        $attributes['branch_id'] = $branch->id;
        $attributes['type'] ??= 'radius';
        $attributes['is_active'] ??= true;
        $attributes['sort_order'] ??= 0;

        $zone = DeliveryZone::query()->create($attributes);

        return response()->json(['data' => new DeliveryZoneResource($zone->load('branch'))], 201);
    }

    public function show(DeliveryZone $deliveryZone): JsonResponse
    {
        $this->authorize('view', $deliveryZone);

        return response()->json(['data' => new DeliveryZoneResource($deliveryZone->load('branch'))]);
    }

    public function update(Request $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $this->authorize('update', $deliveryZone);

        $data = $this->validateZone($request, isUpdate: true);

        $deliveryZone->update($this->mapZoneData($data, partial: true));

        return response()->json(['data' => new DeliveryZoneResource($deliveryZone->load('branch'))]);
    }

    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $this->authorize('delete', $deliveryZone);

        $deliveryZone->delete();

        return response()->json(['message' => 'Delivery zone deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateZone(Request $request, bool $isUpdate = false): array
    {
        $sometimes = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'branchId' => [$isUpdate ? 'sometimes' : 'required', 'string', 'exists:branches,uuid'],
            'name' => [$sometimes, 'string', 'max:255'],
            'type' => ['sometimes', 'in:radius,polygon'],
            'radiusKm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'polygon' => ['sometimes', 'nullable', 'array'],
            'deliveryCharge' => [$sometimes, 'integer', 'min:0'],
            'freeDeliveryThreshold' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minOrderAmount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapZoneData(array $data, bool $partial = false): array
    {
        $map = [
            'name' => 'name',
            'type' => 'type',
            'radiusKm' => 'radius_km',
            'polygon' => 'polygon',
            'deliveryCharge' => 'delivery_charge',
            'freeDeliveryThreshold' => 'free_delivery_threshold',
            'minOrderAmount' => 'min_order_amount',
            'isActive' => 'is_active',
            'sortOrder' => 'sort_order',
        ];

        $update = [];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        return $update;
    }
}
