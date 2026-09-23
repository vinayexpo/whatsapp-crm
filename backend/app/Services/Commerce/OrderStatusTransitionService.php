<?php

namespace App\Services\Commerce;

use App\Jobs\NotifyOrderStatusChanged;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Data-driven order status machine: orders.status_id is the source of
 * truth once a company has been provisioned with OrderStatus rows (see
 * OrderStatusProvisioningService); orders.status (plain string slug) is
 * kept in sync alongside it since it's still read by the WhatsApp order
 * engine, OrderResource, and the frontend.
 *
 * Orders that predate provisioning (status_id null) fall back to a
 * minimal terminal-slug check so payment auto-confirmation keeps working
 * without requiring a backfill migration.
 */
class OrderStatusTransitionService
{
    private const LEGACY_TERMINAL_STATUSES = ['cancelled', 'refunded', 'delivered', 'completed'];

    /**
     * Transition an order to a new status, enforcing the target status's
     * allowed_next_status_ids rule set. Throws if the transition is not
     * permitted.
     */
    public function transition(Order $order, OrderStatus $newStatus, ?string $actorDescription = null): void
    {
        $current = $order->status_id ? $order->orderStatus : null;

        if ($current && $current->is_terminal) {
            throw new \RuntimeException("Order #{$order->order_number} is in a terminal status and cannot transition further.");
        }

        if ($current && ! in_array($newStatus->id, $current->allowed_next_status_ids ?? [], true)) {
            throw new \RuntimeException("Transition from '{$current->slug}' to '{$newStatus->slug}' is not allowed.");
        }

        DB::transaction(function () use ($order, $newStatus, $actorDescription) {
            $order->update([
                'status' => $newStatus->slug,
                'status_id' => $newStatus->id,
            ]);

            ActivityLog::query()->create([
                'company_id' => $order->company_id,
                'type' => 'order',
                'description' => $actorDescription ?? "Order #{$order->order_number} status changed to {$newStatus->label}",
                'channel' => 'whatsapp',
                'occurred_at' => now(),
            ]);

            if ($newStatus->notify_customer) {
                NotifyOrderStatusChanged::dispatch($order->id, $newStatus->id);
            }
        });
    }

    /**
     * Resolve the company's OrderStatus row matching a slug, if the company
     * has been provisioned. Returns null for unprovisioned companies.
     */
    public function resolveBySlug(int $companyId, string $slug): ?OrderStatus
    {
        return OrderStatus::query()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->first();
    }

    public function maybeAutoConfirmOnPayment(Order $order): void
    {
        if (! $order->status_id) {
            $this->legacyAutoConfirmOnPayment($order);

            return;
        }

        $current = $order->orderStatus;

        if (! $current || $current->is_terminal) {
            return;
        }

        if ($current->slug === 'pending') {
            $confirmed = $this->resolveBySlug($order->company_id, 'confirmed');

            if ($confirmed && in_array($confirmed->id, $current->allowed_next_status_ids ?? [], true)) {
                $order->update(['payment_status' => 'paid']);
                $this->transition($order, $confirmed, "Order #{$order->order_number} auto-confirmed after payment");

                return;
            }
        }

        $order->update(['payment_status' => 'paid']);
    }

    private function legacyAutoConfirmOnPayment(Order $order): void
    {
        if (in_array($order->status, self::LEGACY_TERMINAL_STATUSES, true)) {
            return;
        }

        if ($order->status === 'pending') {
            $order->update(['status' => 'confirmed', 'payment_status' => 'paid']);

            return;
        }

        $order->update(['payment_status' => 'paid']);
    }
}
