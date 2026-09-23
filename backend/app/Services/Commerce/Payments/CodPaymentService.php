<?php

namespace App\Services\Commerce\Payments;

use App\Models\Payment;

/**
 * Cash on delivery: nothing to initiate with an external party. The Payment
 * row stays 'pending' until staff marks it paid (typically on delivery/order
 * completion) via PaymentController::markPaid().
 */
class CodPaymentService implements PaymentServiceInterface
{
    public function initiate(Payment $payment): string
    {
        return 'cod-'.$payment->uuid;
    }

    public function handleCallback(Payment $payment, array $payload): string
    {
        return $payment->status;
    }
}
