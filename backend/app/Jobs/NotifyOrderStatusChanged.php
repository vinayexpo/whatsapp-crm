<?php

namespace App\Jobs;

use App\Jobs\Concerns\NotifiesOnFailure;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Commerce\CommerceMessageSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class NotifyOrderStatusChanged implements ShouldQueue
{
    use Queueable, NotifiesOnFailure;

    public function __construct(public int $orderId, public int $orderStatusId) {}

    public function handle(CommerceMessageSender $sender): void
    {
        $order = Order::query()->with('conversation')->find($this->orderId);
        $status = OrderStatus::query()->with('notificationTemplate')->find($this->orderStatusId);

        if (! $order || ! $order->conversation || ! $status) {
            return;
        }

        $text = $status->notificationTemplate
            ? $this->renderTemplate($status->notificationTemplate->body, $order, $status)
            : "Order #{$order->order_number} update: {$status->label}.";

        $sender->send($order->conversation, $text);
    }

    private function renderTemplate(string $body, Order $order, OrderStatus $status): string
    {
        return strtr($body, [
            '{{order_number}}' => $order->order_number,
            '{{status}}' => $status->label,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $order = Order::query()->find($this->orderId);
        $this->recordFailure($e, $order?->company_id);
    }
}
