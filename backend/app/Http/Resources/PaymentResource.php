<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'orderId' => $this->order?->uuid,
            'orderNumber' => $this->order?->order_number,
            'method' => $this->method,
            'amount' => $this->amount,
            'status' => $this->status,
            'providerReference' => $this->provider_reference,
            'failureReason' => $this->failure_reason,
            'paidAt' => $this->paid_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
