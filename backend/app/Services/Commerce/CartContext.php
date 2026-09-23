<?php

namespace App\Services\Commerce;

use App\Models\OrderSession;
use Illuminate\Support\Str;

/**
 * Thin wrapper around OrderSession::context (json) for cart manipulation.
 * Mirrors the context shape documented in the plan:
 * { branch, cart: [...], cart_total, customer, fulfillment, payment, nav }.
 */
class CartContext
{
    /** @var array<string, mixed> */
    private array $context;

    public function __construct(private OrderSession $session)
    {
        $this->context = $session->context ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cart(): array
    {
        return $this->context['cart'] ?? [];
    }

    /**
     * @param  array<int, array{addon_definition_id: int, name: string, price: int, quantity: int}>  $addons
     */
    public function addItem(int $productId, ?int $variantId, string $name, int $unitPrice, int $quantity, array $addons = []): void
    {
        $addonsTotal = array_sum(array_map(fn ($a) => $a['price'] * $a['quantity'], $addons));
        $lineTotal = ($unitPrice + $addonsTotal) * $quantity;

        $cart = $this->cart();
        $cart[] = [
            'line_id' => Str::random(8),
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'name' => $name,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'addons' => $addons,
            'line_total' => $lineTotal,
        ];

        $this->context['cart'] = $cart;
        $this->recalculateTotal();
    }

    public function removeItem(string $lineId): void
    {
        $this->context['cart'] = array_values(array_filter(
            $this->cart(),
            fn (array $line) => $line['line_id'] !== $lineId
        ));
        $this->recalculateTotal();
    }

    public function isEmpty(): bool
    {
        return empty($this->cart());
    }

    public function total(): int
    {
        return $this->context['cart_total'] ?? 0;
    }

    public function setNav(string $key, mixed $value): void
    {
        $this->context['nav'] ??= [];
        $this->context['nav'][$key] = $value;
    }

    public function nav(string $key): mixed
    {
        return $this->context['nav'][$key] ?? null;
    }

    public function setBranch(int $id, string $name): void
    {
        $this->context['branch'] = ['id' => $id, 'name' => $name];
    }

    public function branchId(): ?int
    {
        return $this->context['branch']['id'] ?? null;
    }

    public function setCustomer(string $key, mixed $value): void
    {
        $this->context['customer'] ??= [];
        $this->context['customer'][$key] = $value;
    }

    public function customer(string $key): mixed
    {
        return $this->context['customer'][$key] ?? null;
    }

    public function setFulfillment(string $type, int $deliveryCharge = 0): void
    {
        $this->context['fulfillment'] = [
            'type' => $type,
            'delivery_charge' => $deliveryCharge,
            'lat' => $this->context['fulfillment']['lat'] ?? null,
            'lng' => $this->context['fulfillment']['lng'] ?? null,
        ];
    }

    public function setDeliveryCoordinates(?float $lat, ?float $lng): void
    {
        $this->context['fulfillment'] ??= ['type' => 'delivery', 'delivery_charge' => 0];
        $this->context['fulfillment']['lat'] = $lat;
        $this->context['fulfillment']['lng'] = $lng;
    }

    public function fulfillment(): ?array
    {
        return $this->context['fulfillment'] ?? null;
    }

    public function setPaymentMethod(string $method): void
    {
        $this->context['payment'] = ['method' => $method];
    }

    public function paymentMethod(): ?string
    {
        return $this->context['payment']['method'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->context;
    }

    public function persist(string $step): void
    {
        $this->session->update([
            'context' => $this->context,
            'step' => $step,
            'last_interaction_at' => now(),
        ]);
    }

    private function recalculateTotal(): void
    {
        $this->context['cart_total'] = array_sum(array_column($this->cart(), 'line_total'));
    }
}
