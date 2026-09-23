<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'branchId' => $this->branch?->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'radiusKm' => $this->radius_km,
            'polygon' => $this->polygon,
            'deliveryCharge' => $this->delivery_charge,
            'freeDeliveryThreshold' => $this->free_delivery_threshold,
            'minOrderAmount' => $this->min_order_amount,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
