<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\OrderStatusTransitionService;
use App\Services\Commerce\Payments\RazorpayPaymentService;
use App\Services\Commerce\Payments\WhatsAppPayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        return PaymentResource::collection(
            Payment::query()
                ->with('order')
                ->when($request->filled('order_id'), fn ($q) => $q->whereHas('order', fn ($o) => $o->where('uuid', $request->query('order_id'))))
                ->orderByDesc('created_at')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function markPaid(Payment $payment): JsonResponse
    {
        $this->authorize('update', $payment);

        if (! in_array($payment->method, ['cod', 'upi'], true)) {
            return response()->json(['message' => 'Only cod/upi payments can be manually confirmed.'], 422);
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        app(OrderStatusTransitionService::class)->maybeAutoConfirmOnPayment($payment->order);

        return response()->json(['data' => new PaymentResource($payment->fresh('order'))]);
    }

    /**
     * Razorpay webhook: POST /api/webhooks/commerce-payment/razorpay.
     * Verifies X-Razorpay-Signature before touching anything.
     */
    public function razorpayWebhook(Request $request): Response
    {
        $service = app(RazorpayPaymentService::class);
        $signature = $request->header('X-Razorpay-Signature', '');

        if (! $service->verifySignature($request->getContent(), $signature)) {
            return response()->noContent(401);
        }

        $payload = $request->all();
        $razorpayOrderId = data_get($payload, 'payload.payment.entity.order_id');

        $payment = Payment::query()->where('provider_reference', $razorpayOrderId)->first();

        if (! $payment) {
            Log::warning('PaymentController: razorpay webhook for unknown payment', ['order_id' => $razorpayOrderId]);

            return response()->noContent();
        }

        $service->handleCallback($payment, $payload);

        return response()->noContent();
    }

    /**
     * WhatsApp Pay payment_status updates arrive via the standard WhatsApp
     * webhook (WhatsAppWebhookController), not a separate endpoint --
     * matched by reference_id back to payments.provider_reference. Exposed
     * here as a small helper the webhook job can call.
     */
    public function handleWhatsAppPayStatus(string $referenceId, array $payload): void
    {
        $payment = Payment::query()->where('provider_reference', $referenceId)->first();

        if (! $payment) {
            Log::warning('PaymentController: whatsapp pay status for unknown payment', ['reference_id' => $referenceId]);

            return;
        }

        app(WhatsAppPayPaymentService::class)->handleCallback($payment, $payload);
    }
}
