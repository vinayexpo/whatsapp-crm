<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'branchId' => $this->branch?->uuid,
            'branchName' => $this->branch?->name,
            'contactId' => $this->contact?->uuid,
            'contactName' => $this->contact?->name,
            'conversationId' => $this->conversation?->uuid,
            'orderNumber' => $this->order_number,
            'status' => $this->status,
            'fulfillmentType' => $this->fulfillment_type,
            'deliveryAddress' => $this->delivery_address,
            'deliveryLat' => $this->delivery_lat,
            'deliveryLng' => $this->delivery_lng,
            'subtotal' => $this->subtotal,
            'taxTotal' => $this->tax_total,
            'deliveryCharge' => $this->delivery_charge,
            'discountTotal' => $this->discount_total,
            'grandTotal' => $this->grand_total,
            'currency' => $this->currency,
            'paymentMethod' => $this->payment_method,
            'paymentStatus' => $this->payment_status,
            'assignedStaffUserId' => $this->assignedStaff?->uuid,
            'notes' => $this->notes,
            'placedAt' => $this->placed_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
