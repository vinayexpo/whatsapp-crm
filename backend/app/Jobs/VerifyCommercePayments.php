<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Commerce\Payments\WhatsAppPayPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyCommercePayments implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $companyId) {}

    public function handle(WhatsAppPayPaymentService $service): void
    {
        Payment::where('company_id', $this->companyId)->where('method', 'gateway:whatsapp')->where('status', 'pending')->whereNotNull('provider_reference')
            ->orderBy('updated_at')->limit(10)->get()->each(function ($payment) use ($service) {
                try {
                    $service->verify($payment);
                } catch (\Throwable $e) {
                    report($e);
                }
                $payment->touch();
            });
    }
}
