<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid, 'name' => $this->name, 'email' => $this->email,
            'role' => $this->role(), 'status' => $this->suspended_at ? 'suspended' : $this->status,
            'companyId' => $this->company?->uuid, 'companyName' => $this->company?->name,
            'companyStatus' => $this->company?->status,
            'staffBranchId' => $this->staffBranch?->uuid, 'staffBranchName' => $this->staffBranch?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
