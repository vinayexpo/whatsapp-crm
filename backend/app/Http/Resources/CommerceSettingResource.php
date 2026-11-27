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
            'apiConnectionId' => $this->apiConnection?->uuid,
            'syncEnabled' => $this->sync_enabled,
            'lastSyncAt' => $this->last_sync_at?->toIso8601String(),
            'syncError' => $this->sync_error,
            'paymentConfigurationName' => $this->payment_configuration_name,
            'whatsappPayEnabled' => $this->whatsapp_pay_enabled,
            'razorpayConfigured' => filled($this->razorpay_key_id) && filled($this->razorpay_key_secret),
            'razorpayWebhookConfigured' => filled($this->razorpay_webhook_secret),
            'currency' => $this->currency,
            'defaultTaxRateBp' => $this->default_tax_rate_bp,
            'orderNumberPrefix' => $this->order_number_prefix,
            'sessionTimeoutMinutes' => $this->session_timeout_minutes,
            'settings' => $this->settings ?: (object) [],
        ];
    }
}
