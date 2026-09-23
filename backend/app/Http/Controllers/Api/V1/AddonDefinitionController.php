<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\AddonDefinitionResource;
use App\Models\AddonDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddonDefinitionController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AddonDefinition::class);

        return AddonDefinitionResource::collection(
            AddonDefinition::query()
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
                ->when($request->filled('isActive'), fn ($q) => $q->where('is_active', $request->boolean('isActive')))
                ->orderBy('name')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AddonDefinition::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'maxQuantity' => ['sometimes', 'integer', 'min:1'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $addon = AddonDefinition::query()->create([
            'name' => $data['name'],
            'price' => $data['price'] ?? 0,
            'max_quantity' => $data['maxQuantity'] ?? 1,
            'is_active' => $data['isActive'] ?? true,
        ]);

        return response()->json(['data' => new AddonDefinitionResource($addon)], 201);
    }

    public function show(AddonDefinition $addonDefinition): JsonResponse
    {
        $this->authorize('view', $addonDefinition);

        return response()->json(['data' => new AddonDefinitionResource($addonDefinition)]);
    }

    public function update(Request $request, AddonDefinition $addonDefinition): JsonResponse
    {
        $this->authorize('update', $addonDefinition);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'maxQuantity' => ['sometimes', 'integer', 'min:1'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $map = [
            'name' => 'name',
            'price' => 'price',
            'maxQuantity' => 'max_quantity',
            'isActive' => 'is_active',
        ];

        $update = [];
        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        $addonDefinition->update($update);

        return response()->json(['data' => new AddonDefinitionResource($addonDefinition)]);
    }

    public function destroy(AddonDefinition $addonDefinition): JsonResponse
    {
        $this->authorize('delete', $addonDefinition);

        $addonDefinition->delete();

        return response()->json(['message' => 'Addon deleted.']);
    }
}
