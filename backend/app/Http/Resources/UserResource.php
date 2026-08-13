<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'numericId' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatarUrl' => $this->avatar_url,
            'role' => $this->role(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'status' => $this->status,
            'companyId' => $this->company?->uuid,
            'addedAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
