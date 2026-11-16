<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use RuntimeException;

class PickupWeightCapacity
{
    public static function weightKg(Shipment $shipment): ?float
    {
        foreach ([$shipment->verified_weight_kg, $shipment->estimated_weight_kg, $shipment->parcel_weight_kg] as $weight) {
            if (is_numeric($weight) && (float) $weight > 0) {
                return (float) $weight;
            }
        }

        return null;
    }

    public static function enabled(Shipment $shipment): bool
    {
        return !empty($shipment->logistic?->pickup_payload_limits_kg);
    }

    public static function issue(Shipment $shipment, User $rider): ?string
    {
        $limits = $shipment->logistic?->pickup_payload_limits_kg;
        if (empty($limits)) {
            return null;
        }

        $weight = self::weightKg($shipment);
        if ($weight === null) {
            return 'Pickup needs a packed parcel weight before it can be assigned.';
        }

        $vehicle = strtolower(trim((string) $rider->vehicle_type));
        $limit = $limits[$vehicle] ?? null;
        if (!is_numeric($limit) || (float) $limit <= 0) {
            return 'This rider vehicle has no configured pickup weight limit.';
        }

        $remaining = self::grams((float) $limit) - self::grams($weight);
        if ($remaining < 0) {
            return 'The parcel exceeds this rider vehicle’s pickup weight limit.';
        }

        $active = Shipment::query()
            ->whereKeyNot($shipment->id)
            ->whereNotIn('status', [
                'cancelled', 'canceled', 'delivered', 'failed_return_at_sorting_center',
            ])
            ->where(function ($query) use ($rider) {
                $query->where('rider_id', $rider->id)
                    ->orWhere(function ($pickup) use ($rider) {
                        $pickup->where('pickup_rider_id', $rider->id)
                            ->where('received_by_sorting_center', false)
                            ->whereNull('origin_hub_received_at');
                    })
                    ->orWhere(function ($linehaul) use ($rider) {
                        $linehaul->where('linehaul_rider_id', $rider->id)
                            ->whereIn('linehaul_status', [
                                LineHaulAssignmentService::STATUS_ASSIGNED,
                                LineHaulAssignmentService::STATUS_IN_TRANSIT,
                            ]);
                    });
            });

        foreach ($active->cursor() as $parcel) {
            $activeWeight = self::weightKg($parcel);
            if ($activeWeight === null) {
                return 'An active parcel assigned to this rider has no recorded weight.';
            }

            $remaining -= self::grams($activeWeight);
            if ($remaining < 0) {
                return 'The parcel would exceed this rider vehicle’s remaining pickup weight capacity.';
            }
        }

        return null;
    }

    public static function ensureFits(Shipment $shipment, User $rider): void
    {
        $issue = self::issue($shipment, $rider);
        if ($issue !== null) {
            throw new RuntimeException($issue);
        }
    }

    private static function grams(float $kg): int
    {
        return (int) round($kg * 1000);
    }
}
