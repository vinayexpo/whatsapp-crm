<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Jobs\VerifyCommercePayments;
use App\Models\Branch;
use App\Models\CommerceSetting;
use App\Models\Company;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Commerce\Payments\WhatsAppPayPaymentService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CommerceIntegrationController extends Controller
{
    public function razorpayWebhook(Request $request, Company $company)
    {
        $settings = CommerceSetting::where('company_id', $company->id)->first();
        $secret = $settings?->razorpay_webhook_secret;
        if (! $secret || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Razorpay-Signature'))) {
            return response()->noContent(401);
        }
        VerifyCommercePayments::dispatch($company->id);

        return response()->noContent();
    }

    public function overview(Request $request)
    {
        $this->authorize('manage', CommerceSetting::class);

        return response()->json(['data' => [
            'products' => Product::count(), 'locations' => Branch::count(), 'orders' => Order::count(),
            'conflicts' => Product::where('meta_sync_state', 'conflict')->count(),
            'pendingSync' => Product::where('meta_sync_state', 'pending')->count(),
            'syncErrors' => Product::where('meta_sync_state', 'error')->count(),
            'collected' => Payment::where('status', 'paid')->sum('amount'),
            'awaitingPayment' => Payment::where('status', 'pending')->sum('amount'),
        ]]);
    }

    public function check(Request $request)
    {
        $this->authorize('manage', CommerceSetting::class);
        $settings = CommerceSetting::where('company_id', $request->user()->company_id)->firstOrFail();
        $connection = $settings->apiConnection;
        abort_unless($connection?->access_token && $settings->meta_catalog_id, 422, 'Save a WhatsApp connection and catalog first.');
        $base = 'https://graph.facebook.com/'.config('services.meta.graph_version', 'v25.0');
        $catalog = $this->provider(fn () => Http::withToken($connection->access_token)->connectTimeout(5)->timeout(15)->get($base.'/'.$settings->meta_catalog_id, ['fields' => 'id,name'])->throw());
        $payment = false;
        if ($settings->payment_configuration_name && $connection->waba_id) {
            $config = $this->provider(fn () => Http::withToken($connection->access_token)->connectTimeout(5)->timeout(15)
                ->get($base.'/'.$connection->waba_id.'/payment_configuration/'.rawurlencode($settings->payment_configuration_name))->throw()->json());
            $state = strtolower(str_replace([' ', '_'], '', (string) data_get($config, 'status', data_get($config, 'data.0.status', ''))));
            $payment = $state === 'active';
        }
        $razorpay = false;
        if ($settings->razorpay_key_id && $settings->razorpay_key_secret) {
            $razorpay = Http::withBasicAuth($settings->razorpay_key_id, $settings->razorpay_key_secret)->connectTimeout(5)->timeout(15)
                ->get('https://api.razorpay.com/v1/payments', ['count' => 1])->successful();
        }

        return response()->json(['data' => ['catalog' => $catalog->json('name'), 'paymentConfigurationActive' => $payment, 'razorpayVerified' => $razorpay]]);
    }

    public function requestPayment(Request $request, Order $order, WhatsAppPayPaymentService $service)
    {
        $this->authorize('update', $order);
        abort_unless($request->user()->can('payments.manage'), 403);
        abort_if($order->payment_status === 'paid' || $order->status === 'cancelled', 422, 'This order cannot accept another payment request.');
        $payment = Cache::lock('commerce-order-pay:'.$order->id, 45)->block(3, function () use ($order, $service) {
            $payment = Payment::where('order_id', $order->id)->where('method', 'gateway:whatsapp')->where('status', 'pending')->first();
            if (! $payment) {
                $payment = Payment::create(['company_id' => $order->company_id, 'order_id' => $order->id, 'method' => 'gateway:whatsapp', 'amount' => $order->grand_total, 'status' => 'pending']);
            }
            $this->provider(fn () => $service->initiate($payment));
            $order->update(['payment_method' => 'gateway:whatsapp']);

            return $payment;
        });

        return response()->json(['data' => new PaymentResource($payment->fresh('order'))]);
    }

    public function verifyPayment(Payment $payment, WhatsAppPayPaymentService $service)
    {
        $this->authorize('update', $payment);
        $this->provider(fn () => $service->verify($payment));

        return response()->json(['data' => new PaymentResource($payment->fresh('order'))]);
    }

    private function provider(callable $action): mixed
    {
        try {
            return $action();
        } catch (RequestException|ConnectionException $e) {
            report($e);
            throw ValidationException::withMessages(['integration' => 'The provider request could not complete. Check the saved credentials, account activation and permissions, then retry.']);
        }
    }
}
