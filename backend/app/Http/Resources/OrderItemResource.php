<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->product?->uuid,
            'productVariantId' => $this->productVariant?->uuid,
            'nameSnapshot' => $this->name_snapshot,
            'unitPriceSnapshot' => $this->unit_price_snapshot,
            'quantity' => $this->quantity,
            'selectedOptions' => $this->selected_options,
            'lineTotal' => $this->line_total,
        ];
    }
}
