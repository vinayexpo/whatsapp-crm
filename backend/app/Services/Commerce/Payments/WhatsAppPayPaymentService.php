<?php

namespace App\Services\Commerce\Payments;

use App\Events\ConversationUpdated;
use App\Events\MessageReceived;
use App\Models\ApiConnection;
use App\Models\CommerceSetting;
use App\Models\Message;
use App\Models\Payment;
use App\Services\Commerce\OrderStatusTransitionService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WhatsAppPayPaymentService implements PaymentServiceInterface
{
    public function __construct(private OrderStatusTransitionService $transitions) {}

    public static function isConfigured(?int $companyId = null): bool
    {
        $companyId ??= auth()->user()?->company_id;
        if (! $companyId) {
            return false;
        }
        $settings = CommerceSetting::where('company_id', $companyId)->first();

        return $settings && $settings->whatsapp_pay_enabled && $settings->currency === 'INR' && filled($settings->payment_configuration_name)
            && filled($settings->razorpay_key_id) && filled($settings->razorpay_key_secret) && filled($settings->apiConnection?->access_token);
    }

    public function initiate(Payment $payment): string
    {
        return Cache::lock('commerce-pay:'.$payment->id, 45)->block(3, function () use ($payment) {
            $payment->refresh();
            if ($payment->provider_reference) {
                return $payment->provider_reference;
            }
            $order = $payment->order()->with(['items.product', 'contact', 'branch'])->firstOrFail();
            $settings = CommerceSetting::where('company_id', $order->company_id)->first();
            if (! self::isConfigured($order->company_id) || $order->currency !== 'INR' || $payment->amount !== $order->grand_total || $payment->amount < 100 || $order->payment_status === 'paid' || $order->status === 'cancelled') {
                throw ValidationException::withMessages(['payment' => 'Set up India/Razorpay payments first. Only unpaid INR orders of at least ₹1 can request payment.']);
            }
            $connection = $settings->apiConnection;
            if (! $order->contact || $order->contact->channel !== 'whatsapp' || ! $connection->phone_number_id) {
                throw ValidationException::withMessages(['payment' => 'This order needs a WhatsApp customer and a connected business phone number.']);
            }
            $reference = str_replace('-', '', $payment->uuid);
            $value = fn ($amount) => ['value' => (int) $amount, 'offset' => 100];
            $parameters = [
                'reference_id' => $reference, 'type' => 'physical-goods', 'currency' => 'INR',
                'payment_settings' => [['type' => 'payment_gateway', 'payment_gateway' => ['type' => 'razorpay', 'configuration_name' => $settings->payment_configuration_name]]],
                'total_amount' => $value($payment->amount),
                'order' => ['status' => 'pending', 'items' => $order->items->map(fn ($item) => [
                    'retailer_id' => $item->product?->meta_retailer_id ?: (string) $item->product_id,
                    'name' => $item->name_snapshot, 'amount' => $value(intdiv($item->line_total, max(1, (int) $item->quantity))), 'quantity' => (int) $item->quantity,
                ])->values()->all(), 'subtotal' => $value($order->subtotal), 'tax' => $value($order->tax_total),
                    'shipping' => $value($order->delivery_charge), 'discount' => $value($order->discount_total)],
            ];
            if ($order->items->every(fn ($item) => $item->product?->is_service)) {
                $parameters['type'] = 'digital-goods';
            }
            // Record the reference and original account before sending; a timeout never creates a second bill.
            $payment->update(['provider_reference' => $reference, 'raw_payload' => ['configuration' => $settings->payment_configuration_name, 'connection_id' => $connection->id]]);
            try {
                $response = Http::withToken($connection->access_token)->connectTimeout(5)->timeout(25)
                    ->post($this->base()."/{$connection->phone_number_id}/messages", ['messaging_product' => 'whatsapp', 'to' => $order->contact->handle, 'type' => 'interactive',
                        'interactive' => ['type' => 'order_details', 'body' => ['text' => "Review and pay for order #{$order->order_number}."], 'action' => ['name' => 'review_and_pay', 'parameters' => $parameters]]])->throw();
                if (! $response->json('messages.0.id')) {
                    throw new \RuntimeException('Meta did not return a payment message ID.');
                }
                $payment->update(['failure_reason' => null]);
                if ($order->conversation_id) {
                    $message = Message::create(['conversation_id' => $order->conversation_id, 'direction' => 'outbound', 'text' => "Payment requested for order #{$order->order_number} through WhatsApp (Razorpay).", 'status' => 'sent', 'external_message_id' => $response->json('messages.0.id'), 'sent_at' => now()]);
                    $order->conversation->update(['last_message_at' => $message->sent_at]);
                    MessageReceived::dispatch($message->load('conversation'));
                    ConversationUpdated::dispatch($order->conversation);
                }
            } catch (\Throwable $e) {
                $payment->update(['failure_reason' => 'Payment request could not be confirmed. Verify payment status before trying again.']);
                if ($e instanceof RequestException && $e->response->status() === 400) {
                    $payment->update(['status' => 'failed', 'failure_reason' => 'Meta rejected the payment request. Check payment setup, product details and the customer messaging window.']);
                }
                throw $e;
            }

            return $reference;
        });
    }

    public function handleCallback(Payment $payment, array $payload): string
    {
        // Webhook content only triggers a trusted lookup; it never marks money received itself.
        return $this->verify($payment);
    }

    public function verify(Payment $payment): string
    {
        if ($payment->status === 'paid') {
            return 'paid';
        }
        if (! $payment->provider_reference || $payment->method !== 'gateway:whatsapp') {
            return $payment->status;
        }
        $connection = ApiConnection::where('company_id', $payment->company_id)->find(data_get($payment->raw_payload, 'connection_id'));
        $configuration = data_get($payment->raw_payload, 'configuration');
        if (! $connection?->access_token || ! $configuration) {
            throw new \RuntimeException('The original payment connection is unavailable.');
        }
        $response = Http::withToken($connection->access_token)->connectTimeout(5)->timeout(15)->get($this->base().'/'.$connection->phone_number_id.'/payments/'.rawurlencode($configuration).'/'.rawurlencode($payment->provider_reference))->throw();
        $receipt = $response->json('payments.0') ?? $response->json('data.0') ?? $response->json();
        $amount = data_get($receipt, 'amount.value', data_get($receipt, 'total_amount.value', data_get($receipt, 'amount')));
        $offset = data_get($receipt, 'amount.offset', data_get($receipt, 'total_amount.offset', 100));
        if (($receipt['reference_id'] ?? null) !== $payment->provider_reference || ($receipt['currency'] ?? null) !== 'INR' || (int) $amount !== $payment->amount || (int) $offset !== 100) {
            throw ValidationException::withMessages(['payment' => 'The provider receipt does not match this payment reference, currency and amount.']);
        }

        return DB::transaction(function () use ($payment, $receipt) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'paid') {
                return 'paid';
            }
            if (($receipt['status'] ?? '') === 'captured') {
                $locked->update(['status' => 'paid', 'paid_at' => now(), 'failure_reason' => null, 'raw_payload' => array_merge($locked->raw_payload ?? [], ['verified_receipt' => $receipt])]);
                $locked->order->update(['payment_status' => 'paid']);
                $this->transitions->maybeAutoConfirmOnPayment($locked->order);

                return 'paid';
            }

            return $locked->status;
        });
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version', 'v25.0');
    }
}
