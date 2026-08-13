<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DailyMetric */
class DailyMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date->toDateString(),
            'whatsappSent' => $this->whatsapp_sent,
            'instagramSent' => $this->instagram_sent,
            'delivered' => $this->delivered,
            'read' => $this->read,
            'replied' => $this->replied,
            'avgResponseMinutes' => $this->avg_response_minutes,
        ];
    }
}
