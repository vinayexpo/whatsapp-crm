<?php

namespace App\Services\Commerce\Payments;

use App\Models\Payment;

/**
 * Mirrors VoiceCallServiceInterface's shape (App\Services\Voice).
 */
interface PaymentServiceInterface
{
    /**
     * Initiate/record a payment attempt for the given Payment row and return
     * a provider reference (synthetic for COD/UPI). May send a WhatsApp
     * message to the customer (payment link, QR, or an Order Details
     * interactive message) as a side effect.
     */
    public function initiate(Payment $payment): string;

    /**
     * Verify and apply an async callback/webhook payload, returning the
     * resolved status: 'paid', 'failed', or 'pending'.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(Payment $payment, array $payload): string;
}
