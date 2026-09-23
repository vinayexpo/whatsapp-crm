<?php

namespace App\Http\Resources;

use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allowedIds = $this->allowed_next_status_ids ?? [];
        $siblingUuids = OrderStatus::query()->whereIn('id', $allowedIds)->pluck('uuid', 'id');

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'label' => $this->label,
            'sortOrder' => $this->sort_order,
            'isTerminal' => $this->is_terminal,
            'isCancellableFrom' => $this->is_cancellable_from,
            'notifyCustomer' => $this->notify_customer,
            'notificationTemplateId' => $this->notificationTemplate?->uuid,
            'allowedNextStatusIds' => collect($allowedIds)->map(fn ($id) => $siblingUuids->get($id))->filter()->values(),
        ];
    }
}
