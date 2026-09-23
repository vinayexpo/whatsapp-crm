<?php

namespace App\Services\Commerce\Payments;

use App\Models\Payment;
use App\Services\Commerce\OrderStatusTransitionService;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayPaymentService implements PaymentServiceInterface
{
    public function __construct(private OrderStatusTransitionService $transitions) {}

    public static function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public function initiate(Payment $payment): string
    {
        $api = $this->api();

        // Razorpay Orders API expects the amount in the smallest currency
        // unit (paise for INR); payments.amount is already stored that way.
        $razorpayOrder = $api->order->create([
            'amount' => $payment->amount,
            'currency' => $payment->order->currency ?? 'INR',
            'receipt' => (string) $payment->order->order_number,
            'notes' => [
                'payment_uuid' => $payment->uuid,
                'order_id' => (string) $payment->order_id,
            ],
        ]);

        $payment->update(['provider_reference' => $razorpayOrder->id]);

        return $razorpayOrder->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(Payment $payment, array $payload): string
    {
        $event = (string) data_get($payload, 'event');
        $entity = data_get($payload, 'payload.payment.entity', []);

        if ($event === 'payment.captured') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'raw_payload' => $payload,
            ]);

            $this->transitions->maybeAutoConfirmOnPayment($payment->order);

            return 'paid';
        }

        if ($event === 'payment.failed') {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => data_get($entity, 'error_description'),
                'raw_payload' => $payload,
            ]);

            return 'failed';
        }

        Log::info('RazorpayPaymentService: unhandled webhook event', ['event' => $event, 'payment_id' => $payment->id]);

        return $payment->status;
    }

    /**
     * Verifies X-Razorpay-Signature (HMAC-SHA256 against the configured
     * webhook secret) -- reject-on-mismatch, same shape as
     * WhatsAppWebhookController::hasValidSignature().
     */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        if ($secret === '') {
            return false;
        }

        try {
            $this->api()->utility->verifyWebhookSignature($rawBody, $signature, $secret);

            return true;
        } catch (SignatureVerificationError $e) {
            Log::warning('RazorpayPaymentService: rejected webhook with invalid signature', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function api(): Api
    {
        return new Api(
            (string) config('services.razorpay.key_id'),
            (string) config('services.razorpay.key_secret'),
        );
    }
}
