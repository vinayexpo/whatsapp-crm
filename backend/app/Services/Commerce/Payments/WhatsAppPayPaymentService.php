<?php

namespace App\Services\Commerce\Payments;

use App\Models\ApiConnection;
use App\Models\Payment;
use App\Services\Commerce\OrderStatusTransitionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real Meta WhatsApp Pay: sends an `interactive.type=order_details` message
 * (Meta's native in-chat payment flow, India-only, requires the WABA to be
 * onboarded with a payments provider through Meta) and processes the
 * resulting `payment_status` webhook field.
 *
 * Built against Meta's public WhatsApp Payments API docs without a live
 * WABA/PSP account to verify against -- the exact `order_details` message
 * shape and `payment_status` webhook payload should be confirmed against a
 * real payload before this path is exercised for a real customer, per this
 * codebase's established practice for Meta-API-dependent features (see the
 * WhatsApp Coexistence module's own "verify against current Meta docs"
 * notes).
 */
class WhatsAppPayPaymentService implements PaymentServiceInterface
{
    public function __construct(private OrderStatusTransitionService $transitions) {}

    public static function isConfigured(): bool
    {
        return (bool) config('services.whatsapp_pay.enabled')
            && filled(config('services.whatsapp_pay.payment_configuration_name'));
    }

    public function initiate(Payment $payment): string
    {
        $order = $payment->order()->with(['items', 'contact', 'conversation.apiConnection'])->first();
        $connection = $order->conversation?->apiConnection
            ?? ApiConnection::query()->where('company_id', $order->company_id)->where('channel', 'whatsapp')->first();

        if (! $connection) {
            throw new \RuntimeException('No WhatsApp connection available to send an Order Details payment message.');
        }

        $referenceId = 'wapay-'.$payment->uuid;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $order->contact->handle,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'order_details',
                'body' => ['text' => "Order #{$order->order_number} -- please complete payment to confirm."],
                'action' => [
                    'name' => 'review_and_pay',
                    'parameters' => [
                        'reference_id' => $referenceId,
                        'type' => 'digital-goods',
                        'payment_type' => 'upi',
                        'payment_configuration' => (string) config('services.whatsapp_pay.payment_configuration_name'),
                        'currency' => $order->currency ?? 'INR',
                        'total_amount' => [
                            'value' => $payment->amount,
                            'offset' => 100,
                        ],
                        'order' => [
                            'status' => 'pending',
                            'items' => $order->items->map(fn ($item) => [
                                'retailer_id' => (string) ($item->product_id ?? $item->id),
                                'name' => $item->name_snapshot,
                                'amount' => ['value' => $item->unit_price_snapshot, 'offset' => 100],
                                'quantity' => $item->quantity,
                            ])->values()->all(),
                            'subtotal' => ['value' => $order->subtotal, 'offset' => 100],
                            'tax' => ['value' => $order->tax_total, 'offset' => 100],
                            'shipping' => ['value' => $order->delivery_charge, 'offset' => 100],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($connection->access_token)
            ->post("https://graph.facebook.com/v20.0/{$connection->phone_number_id}/messages", $payload)
            ->throw();

        $payment->update(['provider_reference' => $referenceId]);

        Log::info('WhatsAppPayPaymentService: sent order_details message', [
            'payment_id' => $payment->id,
            'message_id' => $response->json('messages.0.id'),
        ]);

        return $referenceId;
    }

    /**
     * Processes a `payment_status` webhook field embedded in an inbound
     * WhatsApp message payload (delivered via the standard webhook, matched
     * by `reference_id` back to `payments.provider_reference`).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(Payment $payment, array $payload): string
    {
        $status = (string) data_get($payload, 'payment_status.status', data_get($payload, 'status', ''));

        if ($status === 'captured') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'raw_payload' => $payload,
            ]);

            $this->transitions->maybeAutoConfirmOnPayment($payment->order);

            return 'paid';
        }

        if (in_array($status, ['failed', 'declined'], true)) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => (string) data_get($payload, 'payment_status.description', 'Payment failed'),
                'raw_payload' => $payload,
            ]);

            return 'failed';
        }

        Log::info('WhatsAppPayPaymentService: unhandled payment_status', ['status' => $status, 'payment_id' => $payment->id]);

        return $payment->status;
    }
}
