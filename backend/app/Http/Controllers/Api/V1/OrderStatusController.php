<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderStatusResource;
use App\Models\OrderStatus;
use App\Models\WhatsappTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderStatusController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OrderStatus::class);

        return OrderStatusResource::collection(
            OrderStatus::query()
                ->where('company_id', $request->user()->company_id)
                ->with('notificationTemplate')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function update(Request $request, OrderStatus $orderStatus): JsonResponse
    {
        $this->authorize('manage', OrderStatus::class);

        abort_if($orderStatus->company_id !== $request->user()->company_id, 404);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'notifyCustomer' => ['sometimes', 'boolean'],
            'notificationTemplateId' => ['sometimes', 'nullable', 'string', 'exists:whatsapp_templates,uuid'],
            'allowedNextStatusIds' => ['sometimes', 'array'],
            'allowedNextStatusIds.*' => ['string', 'exists:order_statuses,uuid'],
        ]);

        $update = [];

        if (array_key_exists('label', $data)) {
            $update['label'] = $data['label'];
        }

        if (array_key_exists('notifyCustomer', $data)) {
            $update['notify_customer'] = $data['notifyCustomer'];
        }

        if (array_key_exists('notificationTemplateId', $data)) {
            $update['notification_template_id'] = $data['notificationTemplateId']
                ? WhatsappTemplate::query()->where('uuid', $data['notificationTemplateId'])->value('id')
                : null;
        }

        if (array_key_exists('allowedNextStatusIds', $data)) {
            $update['allowed_next_status_ids'] = OrderStatus::query()
                ->where('company_id', $request->user()->company_id)
                ->whereIn('uuid', $data['allowedNextStatusIds'])
                ->pluck('id')
                ->all();
        }

        $orderStatus->update($update);

        return response()->json(['data' => new OrderStatusResource($orderStatus->fresh()->load('notificationTemplate'))]);
    }
}
