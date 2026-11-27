<?php

namespace App\Services\Commerce\Payments;

class PaymentDriverResolver
{
    public function forMethod(string $method, ?int $companyId = null): PaymentServiceInterface
    {
        return match ($method) {
            'cod' => app(CodPaymentService::class),
            'upi' => app(UpiManualPaymentService::class),
            'gateway:razorpay' => RazorpayPaymentService::isConfigured()
                ? app(RazorpayPaymentService::class)
                : throw new \InvalidArgumentException('Razorpay is not configured.'),
            'gateway:whatsapp' => WhatsAppPayPaymentService::isConfigured($companyId)
                ? app(WhatsAppPayPaymentService::class)
                : throw new \InvalidArgumentException('WhatsApp Pay is not configured.'),
            default => throw new \InvalidArgumentException("Unknown payment method: {$method}"),
        };
    }

    /**
     * Methods to surface to the customer in the payment_method conversation
     * step -- Razorpay/WhatsApp Pay silently omitted when unconfigured, so a
     * company never hits a broken gateway path.
     *
     * @return array<int, string>
     */
    public function availableMethods(?int $companyId = null): array
    {
        $methods = ['cod', 'upi'];

        if (WhatsAppPayPaymentService::isConfigured($companyId)) {
            $methods[] = 'gateway:whatsapp';
        }

        return $methods;
    }
}
