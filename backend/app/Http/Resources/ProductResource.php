<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'categoryId' => $this->category?->uuid,
            'brand' => $this->brand,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'images' => $this->images ?? [],
            'basePrice' => $this->base_price,
            'salePrice' => $this->sale_price,
            'taxRateBp' => $this->tax_rate_bp,
            'weightGrams' => $this->weight_grams,
            'dimensions' => $this->dimensions,
            'attributes' => $this->attributes ?? [],
            'deliveryAvailable' => $this->delivery_available,
            'pickupAvailable' => $this->pickup_available,
            'isService' => $this->is_service,
            'isActive' => $this->is_active,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'addons' => AddonDefinitionResource::collection($this->whenLoaded('addonDefinitions')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
