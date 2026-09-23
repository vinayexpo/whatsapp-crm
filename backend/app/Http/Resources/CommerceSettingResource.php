<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommerceSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'businessTypeId' => $this->business_type_id,
            'metaCatalogId' => $this->meta_catalog_id,
            'currency' => $this->currency,
            'defaultTaxRateBp' => $this->default_tax_rate_bp,
            'orderNumberPrefix' => $this->order_number_prefix,
            'sessionTimeoutMinutes' => $this->session_timeout_minutes,
            'settings' => $this->settings ?: (object) [],
        ];
    }
}
