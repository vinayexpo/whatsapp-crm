<?php

namespace App\Jobs;

use App\Jobs\Concerns\NotifiesOnFailure;
use App\Models\Order;
use App\Services\Commerce\CommerceMessageSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendOrderConfirmationMessage implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $orderId) {}

    public function handle(CommerceMessageSender $sender): void
    {
        $order = Order::query()->with(['items', 'conversation'])->find($this->orderId);

        if (! $order || ! $order->conversation) {
            return;
        }

        $lines = $order->items->map(fn ($item) => "{$item->quantity}x {$item->name_snapshot} — {$item->line_total}")->implode("\n");

        $text = "Order #{$order->order_number} confirmed!\n\n{$lines}\n\n".
            "Subtotal: {$order->subtotal}\n".
            "Delivery: {$order->delivery_charge}\n".
            "Total: {$order->grand_total}\n\n".
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
