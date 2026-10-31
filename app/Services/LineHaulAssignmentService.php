<?php

namespace App\Services;

use App\Models\Hub;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LineHaulAssignmentService
{
    public const STATUS_NOT_REQUIRED = 'not_required';
    public const STATUS_AWAITING_ASSIGNMENT = 'awaiting_assignment';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Estimate straight-line distance between hubs.
     *
     * This is intentionally only an estimate. A future Maps API can
     * replace this with actual road distance without changing the
     * line-haul assignment workflow.
     */
    public static function estimateDistanceKm(
        ?Hub $origin,
        ?Hub $destination
    ): ?float {
        if (
            !$origin ||
            !$destination ||
            $origin->latitude === null ||
            $origin->longitude === null ||
            $destination->latitude === null ||
            $destination->longitude === null
        ) {
            return null;
        }

        $earthRadius = 6371;

        $lat1 = deg2rad((float) $origin->latitude);
        $lon1 = deg2rad((float) $origin->longitude);
        $lat2 = deg2rad((float) $destination->latitude);
        $lon2 = deg2rad((float) $destination->longitude);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1)
            * cos($lat2)
            * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return round($earthRadius * $c, 2);
    }

    /**
     * Determine the minimum recommended inter-hub vehicle.
     *
     * Parcel weight takes priority over distance.
     */
    public static function determineRequiredVehicle(
        ?float $distanceKm,
        ?float $weightKg = null
    ): string {
        $distance = max(0, (float) ($distanceKm ?? 0));
        $weight = max(0, (float) ($weightKg ?? 0));

        if ($weight >= 50 || $distance >= 60) {
            return 'truck';
        }

        if ($weight >= 10 || $distance >= 20) {
            return 'van';
        }

        if ($weight >= 5 || $distance >= 10) {
            return 'car';
        }

        return 'motorcycle';
    }

    /**
     * Build the line-haul fields when a shipment is created.
     */
    public static function routingFields(
        ?Hub $origin,
        ?Hub $destination,
        ?float $weightKg = null
    ): array {
        if (
            !$origin ||
            !$destination ||
            (int) $origin->id === (int) $destination->id
        ) {
            return [
                'required_vehicle_type' => null,
                'parcel_weight_kg' => $weightKg,
                'estimated_distance_km' => null,
                'linehaul_status' => self::STATUS_NOT_REQUIRED,
            ];
        }

        $distance = self::estimateDistanceKm(
            $origin,
            $destination
        );

        return [
            'required_vehicle_type' =>
            self::determineRequiredVehicle(
                $distance,
                $weightKg
            ),
            'parcel_weight_kg' => $weightKg,
            'estimated_distance_km' => $distance,
            'linehaul_status' => self::STATUS_AWAITING_ASSIGNMENT,
        ];
    }

    public static function findBestDriver(
        Shipment $shipment
    ): ?User {
        if (
            !$shipment->logistic_id ||
            !$shipment->origin_hub_id ||
            !$shipment->destination_hub_id ||
            (int) $shipment->origin_hub_id ===
            (int) $shipment->destination_hub_id ||
            $shipment->linehaul_rider_id !== null ||
            $shipment->linehaul_status !==
            self::STATUS_AWAITING_ASSIGNMENT
        ) {
            return null;
        }

        $allowedVehicles = self::compatibleVehicles(
            $shipment->required_vehicle_type
        );

        if (empty($allowedVehicles)) {
            return null;
        }

        return User::query()
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('status', User::STATUS_ACTIVE)
            ->where('logistic_status', 'approved')
            ->where('logistic_id', $shipment->logistic_id)
            ->where('hub_id', $shipment->origin_hub_id)
            ->where('availability_status', 'available')
            ->where('max_capacity', '>', 0)
            ->whereColumn('current_load', '<', 'max_capacity')
            ->whereIn(
                DB::raw('LOWER(TRIM(vehicle_type))'),
                $allowedVehicles
            )
            ->orderBy('current_load')
            ->orderBy('id')
            ->first();
    }

    public static function assign(
        Shipment $shipment,
        User $driver
    ): void {
        DB::transaction(function () use ($shipment, $driver) {
            $lockedShipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$lockedShipment->origin_hub_id ||
                !$lockedShipment->destination_hub_id ||
                (int) $lockedShipment->origin_hub_id ===
                (int) $lockedShipment->destination_hub_id
            ) {
                throw new RuntimeException(
                    'This shipment does not require inter-hub line-haul.'
                );
            }

            if (
                $lockedShipment->linehaul_status !==
                self::STATUS_AWAITING_ASSIGNMENT
            ) {
                throw new RuntimeException(
                    'This shipment is not waiting for a line-haul driver.'
                );
            }

            if ($lockedShipment->linehaul_rider_id !== null) {
                throw new RuntimeException(
                    'This shipment already has a line-haul driver.'
                );
            }

            if (
                $lockedShipment->routing_status !== 'at_origin_hub' ||
                $lockedShipment->origin_hub_received_at === null
            ) {
                throw new RuntimeException(
                    'Parcel must be received at the origin hub before line-haul assignment.'
                );
            }

            if (
                (int) $lockedShipment->hub_id !==
                (int) $lockedShipment->origin_hub_id
            ) {
                throw new RuntimeException(
                    'Parcel is not currently located at its origin hub.'
                );
            }

            $lockedDriver = User::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$lockedDriver->roles()
                    ->where('name', 'rider')
                    ->exists()
            ) {
                throw new RuntimeException(
                    'Selected line-haul driver is not a rider.'
                );
            }

            if (
                $lockedDriver->status !== User::STATUS_ACTIVE ||
                $lockedDriver->logistic_status !== 'approved'
            ) {
                throw new RuntimeException(
                    'Only active and approved drivers can perform line-haul transfers.'
                );
            }

            if (
                (int) $lockedDriver->logistic_id !==
                (int) $lockedShipment->logistic_id
            ) {
                throw new RuntimeException(
                    'Line-haul driver must belong to the shipment logistics company.'
                );
            }

            if (
                (int) $lockedDriver->hub_id !==
                (int) $lockedShipment->origin_hub_id
            ) {
                throw new RuntimeException(
                    'Line-haul driver must belong to the shipment origin hub.'
                );
            }

            if (
                $lockedDriver->availability_status !== 'available'
            ) {
                throw new RuntimeException(
                    'Line-haul driver is currently unavailable.'
                );
            }

            $driverVehicle = self::normalizeVehicle(
                $lockedDriver->vehicle_type
            );

            $allowedVehicles = self::compatibleVehicles(
                $lockedShipment->required_vehicle_type
            );

            if (
                !$driverVehicle ||
                !in_array(
                    $driverVehicle,
                    $allowedVehicles,
                    true
                )
            ) {
                throw new RuntimeException(
                    'Driver vehicle does not meet the required vehicle type for this shipment.'
                );
            }

            $currentLoad = (int) $lockedDriver->current_load;
            $maxCapacity = (int) $lockedDriver->max_capacity;

            if (
                $maxCapacity <= 0 ||
                $currentLoad >= $maxCapacity
            ) {
                throw new RuntimeException(
                    'Line-haul driver has reached maximum capacity.'
                );
            }

            $lockedShipment->update([
                'linehaul_rider_id' => $lockedDriver->id,
                'linehaul_status' => self::STATUS_ASSIGNED,
                'linehaul_assigned_at' => now(),
            ]);

            $newLoad = $currentLoad + 1;

            $lockedDriver->update([
                'current_load' => $newLoad,
                'availability_status' =>
                $newLoad >= $maxCapacity
                    ? 'busy'
                    : 'available',
            ]);
        });
    }

    public static function normalizeVehicle(
        ?string $vehicle
    ): ?string {
        if (!$vehicle) {
            return null;
        }

        $value = mb_strtolower(trim($vehicle));

        return match ($value) {
            'motor',
            'motorbike',
            'motor cycle',
            'motorcycle',
            'bike' => 'motorcycle',

            'sedan',
            'car',
            'auto' => 'car',

            'van',
            'mini van',
            'minivan' => 'van',

            'truck',
            'lorry',
            'delivery truck' => 'truck',

            default => $value,
        };
    }

    /**
     * Larger vehicles may carry a shipment whose minimum
     * requirement is smaller.
     */
    public static function compatibleVehicles(
        ?string $required
    ): array {
        return match (self::normalizeVehicle($required)) {
            'motorcycle' => [
                'motorcycle',
                'car',
                'van',
                'truck',
            ],

            'car' => [
                'car',
                'van',
                'truck',
            ],

            'van' => [
                'van',
                'truck',
            ],

            'truck' => [
                'truck',
            ],

            default => [],
        };
    }
}
