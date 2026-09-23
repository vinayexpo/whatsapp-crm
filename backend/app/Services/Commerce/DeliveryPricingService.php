<?php

namespace App\Services\Commerce;

use App\Models\Branch;
use App\Models\DeliveryZone;

/**
 * Resolves the delivery charge for an order: the matching DeliveryZone (by
 * distance from the branch, when lat/lng is available) takes priority over
 * the branch's flat default_delivery_charge. Zones are evaluated in
 * sort_order, first match wins.
 */
class DeliveryPricingService
{
    /**
     * @return array{delivery_charge: int, zone_id: int|null}
     */
    public function priceFor(Branch $branch, int $subtotal, ?float $lat = null, ?float $lng = null): array
    {
        $zone = $this->matchZone($branch, $lat, $lng);

        if ($zone) {
            if ($zone->min_order_amount !== null && $subtotal < $zone->min_order_amount) {
                return ['delivery_charge' => 0, 'zone_id' => null, 'below_minimum' => true, 'min_order_amount' => $zone->min_order_amount];
            }

            if ($zone->free_delivery_threshold !== null && $subtotal >= $zone->free_delivery_threshold) {
                return ['delivery_charge' => 0, 'zone_id' => $zone->id];
            }

            return ['delivery_charge' => $zone->delivery_charge, 'zone_id' => $zone->id];
        }

        if ($branch->min_order_amount !== null && $subtotal < $branch->min_order_amount) {
            return ['delivery_charge' => 0, 'zone_id' => null, 'below_minimum' => true, 'min_order_amount' => $branch->min_order_amount];
        }

        return ['delivery_charge' => $branch->default_delivery_charge ?? 0, 'zone_id' => null];
    }

    private function matchZone(Branch $branch, ?float $lat, ?float $lng): ?DeliveryZone
    {
        $zones = DeliveryZone::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($zones->isEmpty()) {
            return null;
        }

        if ($lat === null || $lng === null || $branch->latitude === null || $branch->longitude === null) {
            // No coordinates to test radius zones against -- fall back to the
            // first active zone with no radius constraint (a flat zone), if any.
            return $zones->firstWhere('radius_km', null);
        }

        foreach ($zones as $zone) {
            if ($zone->type !== 'radius' || $zone->radius_km === null) {
                continue;
            }

            $distanceKm = $this->haversineKm((float) $branch->latitude, (float) $branch->longitude, $lat, $lng);

            if ($distanceKm <= (float) $zone->radius_km) {
                return $zone;
            }
        }

        return null;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
