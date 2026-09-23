<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'apiConnectionId' => $this->apiConnection?->uuid,
            'businessTypeId' => $this->businessType?->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'status' => $this->status,
            'operatingHours' => $this->operating_hours,
            'holidays' => $this->holidays,
            'timezone' => $this->timezone,
            'minOrderAmount' => $this->min_order_amount,
            'defaultDeliveryCharge' => $this->default_delivery_charge,
            'deliveryRadiusKm' => $this->delivery_radius_km,
            'currency' => $this->currency,
            'isDefault' => $this->is_default,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
