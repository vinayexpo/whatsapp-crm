<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformCompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid, 'name' => $this->name, 'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'usersCount' => $this->whenCounted('users'),
            'activeAdminsCount' => $this->whenHas('active_admins_count'),
            'contactsCount' => $this->whenCounted('contacts'),
            'conversationsCount' => $this->whenCounted('conversations'),
            'campaignsCount' => $this->whenCounted('campaigns'),
            'branchesCount' => $this->whenCounted('branches'),
            'ordersCount' => $this->whenCounted('orders'),
            'connectedChannelsCount' => $this->whenHas('connected_channels_count'),
        ];
    }
}
