<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branchId' => $this->branch?->uuid,
            'branchName' => $this->branch?->name,
            'productId' => $this->product?->uuid,
            'productName' => $this->product?->name,
            'productVariantId' => $this->productVariant?->uuid,
            'productVariantName' => $this->productVariant?->name,
            'stockQuantity' => $this->stock_quantity,
            'lowStockThreshold' => $this->low_stock_threshold,
            'trackStock' => $this->track_stock,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
