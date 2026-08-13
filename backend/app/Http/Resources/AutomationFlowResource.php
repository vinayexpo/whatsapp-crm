<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutomationFlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'status' => $this->status,
            'trigger' => $this->trigger,
            'conditions' => $this->conditions ?? [],
            'actions' => $this->actions ?? [],
            'triggeredCount' => $this->triggered_count,
            'lastTriggeredAt' => $this->last_triggered_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
