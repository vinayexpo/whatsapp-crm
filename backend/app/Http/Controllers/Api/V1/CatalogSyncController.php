<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCommerceCatalog;
use App\Models\CommerceSetting;
use App\Models\Product;
use App\Services\Catalog\GraphApiCatalogService;
use App\Services\Catalog\TwoWayCatalogSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);
        $settings = $this->settings($request);
        SyncCommerceCatalog::dispatch($settings->id);

        return response()->json(['data' => ['queued' => true]], 202);
    }

    public function push(Request $request, Product $product, TwoWayCatalogSync $sync, GraphApiCatalogService $service): JsonResponse
    {
        $this->authorize('update', $product);
        $settings = $this->settings($request);
        $local = $sync->local($product, $settings->currency);
        $sync->validateForPush($local);
        $key = $product->meta_retailer_id ?: ($product->sku ?: $product->uuid);
        $service->pushProduct($settings->apiConnection, $settings->meta_catalog_id, ['retailer_id' => $key] + $local);
        $product->update(['meta_retailer_id' => $key, 'meta_sync_state' => 'pending', 'meta_sync_error' => null]);

        return response()->json(['data' => ['pushed' => true, 'status' => 'pending']], 202);
    }

    public function resolve(Request $request, Product $product, TwoWayCatalogSync $sync, GraphApiCatalogService $service): JsonResponse
    {
        $this->authorize('update', $product);
        $data = $request->validate(['source' => ['required', 'in:local,meta']]);
        $settings = $this->settings($request);
        $remote = collect($service->fetchProducts($settings->apiConnection, $settings->meta_catalog_id))->firstWhere('retailer_id', $product->meta_retailer_id ?: $product->sku);
        if ($data['source'] === 'meta') {
            if (! $remote || $remote['currency'] !== $settings->currency) {
                throw ValidationException::withMessages(['product' => 'The Meta product is missing or uses another currency.']);
            }
            DB::transaction(fn () => $sync->import($product, $sync->canonical($remote), $remote['retailer_id']));
        } else {
            $result = $this->push($request, $product, $sync, $service);
            $product->update(['meta_snapshot' => $remote ? $sync->canonical($remote) : null]);

            return $result;
        }

        return response()->json(['data' => ['resolved' => true]]);
    }

    private function settings(Request $request): CommerceSetting
    {
        $settings = CommerceSetting::where('company_id', $request->user()->company_id)->first();
        if (! $settings?->meta_catalog_id || ! $settings->apiConnection?->access_token || $settings->apiConnection->company_id !== $settings->company_id) {
            throw ValidationException::withMessages(['catalog' => 'Configure your company WhatsApp connection and Meta catalog in Commerce setup first.']);
        }

        return $settings;
    }
}
