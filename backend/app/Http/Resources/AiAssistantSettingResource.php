<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAssistantSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'baseUrl' => $this->base_url,
            'apiKey' => $this->api_key,
            'model' => $this->model,
        ];
    }
}
