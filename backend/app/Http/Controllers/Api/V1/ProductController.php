<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Models\AddonDefinition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    use PaginatesRequests;

    private const PRODUCT_RELATIONS = ['category', 'variants', 'addonDefinitions'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        return ProductResource::collection(
            Product::query()
                ->with(self::PRODUCT_RELATIONS)
                ->when($request->filled('categoryId'), function ($q) use ($request) {
                    $category = Category::where('uuid', $request->query('categoryId'))->first();
                    $q->where('category_id', $category?->id);
                })
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
                ->when($request->filled('isActive'), fn ($q) => $q->where('is_active', $request->boolean('isActive')))
                ->orderBy('name')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $this->validateProduct($request);

        $product = Product::query()->create($this->mapProductData($data));

        return response()->json(['data' => new ProductResource($product->load(self::PRODUCT_RELATIONS))], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json(['data' => new ProductResource($product->load(self::PRODUCT_RELATIONS))]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $this->validateProduct($request, isUpdate: true);

        $product->update($this->mapProductData($data));

        return response()->json(['data' => new ProductResource($product->load(self::PRODUCT_RELATIONS))]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priceDelta' => ['sometimes', 'integer'],
            'stockTracked' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $variant = $product->variants()->create([
            'name' => $data['name'],
            'attributes' => $data['attributes'] ?? null,
            'sku' => $data['sku'] ?? null,
            'price_delta' => $data['priceDelta'] ?? 0,
            'stock_tracked' => $data['stockTracked'] ?? false,
            'is_active' => $data['isActive'] ?? true,
        ]);

        return response()->json(['data' => new ProductVariantResource($variant)], 201);
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priceDelta' => ['sometimes', 'integer'],
            'stockTracked' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $map = [
            'name' => 'name',
            'attributes' => 'attributes',
            'sku' => 'sku',
            'priceDelta' => 'price_delta',
            'stockTracked' => 'stock_tracked',
            'isActive' => 'is_active',
        ];

        $update = [];
        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        $variant->update($update);

        return response()->json(['data' => new ProductVariantResource($variant)]);
    }

    public function destroyVariant(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);

        $variant->delete();

        return response()->json(['message' => 'Variant deleted.']);
    }

    public function syncAddons(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'addonDefinitionIds' => ['required', 'array'],
            'addonDefinitionIds.*' => ['string', 'exists:addon_definitions,uuid'],
        ]);

        $ids = AddonDefinition::whereIn('uuid', $data['addonDefinitionIds'])->pluck('id');

        $product->addonDefinitions()->sync($ids);

        return response()->json(['data' => new ProductResource($product->load(self::PRODUCT_RELATIONS))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, bool $isUpdate = false): array
    {
        $sometimes = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'categoryId' => ['sometimes', 'nullable', 'string', 'exists:categories,uuid'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => [$sometimes, 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'images' => ['sometimes', 'nullable', 'array'],
            'basePrice' => [$sometimes, 'integer', 'min:0'],
            'salePrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'taxRateBp' => ['sometimes', 'integer', 'min:0'],
            'weightGrams' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dimensions' => ['sometimes', 'nullable', 'array'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'deliveryAvailable' => ['sometimes', 'boolean'],
            'pickupAvailable' => ['sometimes', 'boolean'],
            'isService' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapProductData(array $data): array
    {
        $update = [];

        if (array_key_exists('categoryId', $data)) {
            $update['category_id'] = $data['categoryId']
                ? Category::where('uuid', $data['categoryId'])->value('id')
                : null;
        }

        $map = [
            'brand' => 'brand',
            'name' => 'name',
            'sku' => 'sku',
            'description' => 'description',
            'images' => 'images',
            'basePrice' => 'base_price',
            'salePrice' => 'sale_price',
            'taxRateBp' => 'tax_rate_bp',
            'weightGrams' => 'weight_grams',
            'dimensions' => 'dimensions',
            'attributes' => 'attributes',
            'deliveryAvailable' => 'delivery_available',
            'pickupAvailable' => 'pickup_available',
            'isService' => 'is_service',
            'isActive' => 'is_active',
        ];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        return $update;
    }
}
