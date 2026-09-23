<?php

namespace App\Services\Commerce\Payments;

use App\Models\Payment;

/**
 * Manual UPI: generates a upi:// deep link for the customer to pay via any
 * UPI app, sent as a WhatsApp message by the payment_method step handler.
 * Stays 'pending' until staff manually confirms via PaymentController::markPaid()
 * -- there is no automated callback for manual UPI.
 */
class UpiManualPaymentService implements PaymentServiceInterface
{
    public function initiate(Payment $payment): string
    {
        return 'upi-'.$payment->uuid;
    }

    public function handleCallback(Payment $payment, array $payload): string
    {
        return $payment->status;
    }

    public function buildDeepLink(Payment $payment): string
    {
        $vpa = (string) config('services.upi.vpa');
        $payeeName = (string) (config('services.upi.payee_name') ?: config('app.name'));
        $amountRupees = number_format($payment->amount / 100, 2, '.', '');

        return sprintf(
            'upi://pay?pa=%s&pn=%s&am=%s&cu=INR&tn=%s',
            rawurlencode($vpa),
            rawurlencode($payeeName),
            rawurlencode($amountRupees),
            rawurlencode('Order payment '.$payment->order_id),
        );
    }
}
