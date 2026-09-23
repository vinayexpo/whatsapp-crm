<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'conversationId' => $this->conversation?->uuid,
            'contactId' => $this->contact?->uuid,
            'contactName' => $this->contact?->name,
            'branchId' => $this->branch?->uuid,
            'branchName' => $this->branch?->name,
            'status' => $this->status,
            'step' => $this->step,
            'cartTotal' => $this->context['cart_total'] ?? 0,
            'orderId' => $this->order?->uuid,
            'lastInteractionAt' => $this->last_interaction_at?->toIso8601String(),
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
