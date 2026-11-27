<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommerceSettingResource;
use App\Models\ApiConnection;
use App\Models\BusinessType;
use App\Models\CommerceSetting;
use App\Models\Product;
use App\Services\Commerce\OrderStatusProvisioningService;
use App\Services\Commerce\TriggerKeywordUniquenessValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommerceSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommerceSetting::class);

        $settings = $this->settingsFor($request->user()->company_id);

        return response()->json(['data' => new CommerceSettingResource($settings)]);
    }

    public function update(Request $request, TriggerKeywordUniquenessValidator $validator, OrderStatusProvisioningService $provisioning): JsonResponse
    {
        $this->authorize('manage', CommerceSetting::class);

        $data = $request->validate([
            'businessTypeId' => ['sometimes', 'nullable', 'integer', 'exists:business_types,id'],
            'metaCatalogId' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[0-9]+$/'],
            'apiConnectionId' => ['sometimes', 'nullable', 'uuid'],
            'syncEnabled' => ['sometimes', 'boolean'],
            'paymentConfigurationName' => ['sometimes', 'nullable', 'string', 'max:60'],
            'whatsappPayEnabled' => ['sometimes', 'boolean'],
            'razorpayKeyId' => ['sometimes', 'string', 'max:255', 'starts_with:rzp_'],
            'razorpayKeySecret' => ['sometimes', 'string', 'max:255'],
            'razorpayWebhookSecret' => ['sometimes', 'string', 'min:16', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'defaultTaxRateBp' => ['sometimes', 'integer', 'min:0'],
            'orderNumberPrefix' => ['sometimes', 'nullable', 'string', 'max:16'],
            'sessionTimeoutMinutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'settings' => ['sometimes', 'array'],
            'settings.triggerKeywords' => ['sometimes', 'array'],
            'settings.triggerKeywords.*' => ['string', 'max:64'],
        ]);

        $companyId = $request->user()->company_id;

        if (isset($data['settings']['triggerKeywords'])) {
            $validator->assertNoChatMenuFlowCollision($companyId, $data['settings']['triggerKeywords']);
        }

        $settings = $this->settingsFor($companyId);

        if (array_key_exists('apiConnectionId', $data)) {
            $connection = isset($data['apiConnectionId']) ? ApiConnection::query()->where('company_id', $companyId)->where('channel', 'whatsapp')->where('uuid', $data['apiConnectionId'])->first() : null;
            if ($data['apiConnectionId'] && ! $connection) {
                throw ValidationException::withMessages(['apiConnectionId' => 'Choose a WhatsApp connection belonging to this company.']);
            }
            $settings->api_connection_id = $connection?->id;
        }

        $update = [];
        $map = [
            'businessTypeId' => 'business_type_id',
            'metaCatalogId' => 'meta_catalog_id',
            'currency' => 'currency',
            'defaultTaxRateBp' => 'default_tax_rate_bp',
            'orderNumberPrefix' => 'order_number_prefix',
            'sessionTimeoutMinutes' => 'session_timeout_minutes',
            'syncEnabled' => 'sync_enabled', 'paymentConfigurationName' => 'payment_configuration_name',
            'whatsappPayEnabled' => 'whatsapp_pay_enabled', 'razorpayKeyId' => 'razorpay_key_id',
            'razorpayKeySecret' => 'razorpay_key_secret', 'razorpayWebhookSecret' => 'razorpay_webhook_secret',
        ];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        if (array_key_exists('settings', $data)) {
            $incoming = $data['settings'];
            $merged = array_merge($settings->settings ?? [], [
                'trigger_keywords' => $incoming['triggerKeywords'] ?? ($settings->settings['trigger_keywords'] ?? []),
                'welcome_message' => $incoming['welcomeMessage'] ?? ($settings->settings['welcome_message'] ?? null),
            ]);
            $update['settings'] = $merged;
        }

        $settings->fill($update);
        if ($settings->whatsapp_pay_enabled && ($settings->currency !== 'INR' || ! $settings->payment_configuration_name || ! $settings->api_connection_id || ! $settings->razorpay_key_id || ! $settings->razorpay_key_secret)) {
            throw ValidationException::withMessages(['whatsappPayEnabled' => 'WhatsApp payments require INR, a WhatsApp connection, a Razorpay payment configuration linked in WhatsApp Manager, and Razorpay API credentials.']);
        }
        if ($settings->sync_enabled && (! $settings->api_connection_id || ! $settings->meta_catalog_id)) {
            throw ValidationException::withMessages(['syncEnabled' => 'Choose a WhatsApp connection and Meta catalog before enabling automatic sync.']);
        }
        if ($settings->isDirty(['api_connection_id', 'meta_catalog_id']) && Product::query()->where('company_id', $companyId)->whereNotNull('meta_snapshot')->exists()) {
            throw ValidationException::withMessages(['metaCatalogId' => 'This catalog already has synchronized products. Reset commerce before connecting a different catalog.']);
        }
        $settings->save();

        if (array_key_exists('business_type_id', $update) && $update['business_type_id']) {
            $businessType = BusinessType::query()->find($update['business_type_id']);

            if ($businessType) {
                $provisioning->provisionForCompany($companyId, $businessType);
            }
        }

        return response()->json(['data' => new CommerceSettingResource($settings->fresh())]);
    }

    private function settingsFor(int $companyId): CommerceSetting
    {
        $settings = CommerceSetting::query()->where('company_id', $companyId)->first();

        if (! $settings) {
            $settings = new CommerceSetting([
                'currency' => 'INR',
                'default_tax_rate_bp' => 0,
                'order_number_prefix' => 'ORD',
                'session_timeout_minutes' => 30,
                'settings' => [],
            ]);
            $settings->company_id = $companyId;
            $settings->save();
        }

        return $settings;
    }
}
