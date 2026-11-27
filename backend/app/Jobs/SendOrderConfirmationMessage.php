<?php

namespace App\Jobs;

use App\Jobs\Concerns\NotifiesOnFailure;
use App\Models\Order;
use App\Services\Commerce\CommerceMessageSender;
use App\Services\Commerce\CommerceMoney;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendOrderConfirmationMessage implements ShouldQueue
{
    use NotifiesOnFailure, Queueable;

    public function __construct(public int $orderId) {}

    public function handle(CommerceMessageSender $sender): void
    {
        $order = Order::query()->with(['items', 'conversation'])->find($this->orderId);

        if (! $order || ! $order->conversation) {
            return;
        }

        $lines = $order->items->map(fn ($item) => "{$item->quantity}x {$item->name_snapshot} — ".CommerceMoney::format($item->line_total, $order->currency))->implode("\n");
        $subtotal = CommerceMoney::format($order->subtotal, $order->currency);
        $tax = CommerceMoney::format($order->tax_total, $order->currency);
        $delivery = CommerceMoney::format($order->delivery_charge, $order->currency);
        $total = CommerceMoney::format($order->grand_total, $order->currency);

        $text = "Order #{$order->order_number} received\n\n{$lines}\n\n".
            "Subtotal: {$subtotal}\n".
            "Tax: {$tax}\n".
            "Delivery: {$delivery}\n".
            "Total: {$total}\n\n".
            'Payment: '.strtoupper($order->payment_method)."\n".
            'We\'ll keep you posted on your order status.';

        $sender->send($order->conversation, $text);
    }

    public function failed(Throwable $e): void
    {
        $order = Order::query()->find($this->orderId);
        $this->recordFailure($e, $order?->company_id);
    }
}
