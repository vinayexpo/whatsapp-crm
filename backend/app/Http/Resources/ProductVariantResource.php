<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'productId' => $this->whenLoaded('product', fn () => $this->product->uuid),
            'name' => $this->name,
            'attributes' => $this->attributes,
            'sku' => $this->sku,
            'priceDelta' => $this->price_delta,
            'stockTracked' => $this->stock_tracked,
            'isActive' => $this->is_active,
        ];
    }
}
