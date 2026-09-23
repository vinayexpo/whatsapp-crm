<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommerceSettingResource;
use App\Models\BusinessType;
use App\Models\CommerceSetting;
use App\Services\Commerce\OrderStatusProvisioningService;
use App\Services\Commerce\TriggerKeywordUniquenessValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $update = [];
        $map = [
            'businessTypeId' => 'business_type_id',
            'currency' => 'currency',
            'defaultTaxRateBp' => 'default_tax_rate_bp',
            'orderNumberPrefix' => 'order_number_prefix',
            'sessionTimeoutMinutes' => 'session_timeout_minutes',
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

        $settings->update($update);

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
