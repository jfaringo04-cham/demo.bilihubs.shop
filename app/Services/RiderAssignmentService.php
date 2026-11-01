<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RiderAssignmentService
{
    /**
     * Rider is considered recently online when either:
     * - location was updated recently, or
     * - last activity was recorded recently.
     */
    private const ONLINE_WINDOW_MINUTES = 15;

    /**
     * Return eligible final-mile riders for a shipment.
     *
     * Rules:
     * - rider role
     * - active account
     * - approved by logistics
     * - same logistics company
     * - same destination hub
     * - available
     * - has remaining capacity
     * - same delivery zone when both values exist
     * - recently online
     *
     * When shipment destination coordinates are available,
     * riders are ordered by geographic distance first.
     */
    public static function eligibleRidersForShipment(
        Shipment $shipment
    ): Collection {
        if (!self::shipmentCanReceiveFinalMileAssignment($shipment)) {
            return collect();
        }

        $destinationHubId = $shipment->destination_hub_id
            ?: $shipment->hub_id;

        $shipmentZone = mb_strtolower(
            trim((string) $shipment->delivery_zone)
        );

        $onlineSince = now()->subMinutes(
            self::ONLINE_WINDOW_MINUTES
        );

        $riders = User::query()
            ->with('hub')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'rider');
            })
            ->where('status', User::STATUS_ACTIVE)
            ->where('logistic_status', 'approved')
            ->where(
                'logistic_id',
                $shipment->logistic_id
            )
            ->where(
                'hub_id',
                $destinationHubId
            )
            ->where(
                'availability_status',
                'available'
            )
            ->where(
                'max_capacity',
                '>',
                0
            )
            ->whereColumn(
                'current_load',
                '<',
                'max_capacity'
            )
            ->where(function ($query) use ($onlineSince) {
                $query
                    ->where(
                        'location_updated_at',
                        '>=',
                        $onlineSince
                    )
                    ->orWhere(
                        'last_active_at',
                        '>=',
                        $onlineSince
                    );
            })
            ->get();

        /*
         * Hub membership is the primary dispatch boundary.
         *
         * Delivery-zone validation is retained only when
         * both the shipment and rider have a zone.
         */
        $riders = $riders->filter(
            function (User $rider) use ($shipmentZone) {
                $riderZone = mb_strtolower(
                    trim((string) $rider->assigned_zone)
                );

                if (
                    $shipmentZone !== '' &&
                    $riderZone !== '' &&
                    $shipmentZone !== $riderZone
                ) {
                    return false;
                }

                return true;
            }
        );

        $shipmentLatitude = self::nullableFloat(
            $shipment->latitude
        );

        $shipmentLongitude = self::nullableFloat(
            $shipment->longitude
        );

        /*
         * Build normalized rider rows that the API/mobile
         * layer can consume directly.
         */
        $availableRiders = $riders
            ->map(
                function (User $rider) use (
                    $shipmentLatitude,
                    $shipmentLongitude
                ) {
                    $riderLatitude = self::nullableFloat(
                        $rider->latitude
                    );

                    $riderLongitude = self::nullableFloat(
                        $rider->longitude
                    );

                    $distanceKm = null;

                    if (
                        $shipmentLatitude !== null &&
                        $shipmentLongitude !== null &&
                        $riderLatitude !== null &&
                        $riderLongitude !== null
                    ) {
                        $distanceKm = self::distanceInKilometers(
                            $shipmentLatitude,
                            $shipmentLongitude,
                            $riderLatitude,
                            $riderLongitude
                        );
                    }

                    return [
                        'id' => (int) $rider->id,
                        'rider_id' => (int) $rider->id,

                        'name' => $rider->name,

                        'first_name' => $rider->first_name,
                        'last_name' => $rider->last_name,

                        'phone' =>
                            $rider->phone
                            ?? $rider->mobile_number,

                        'availability_status' =>
                            $rider->availability_status,

                        'status' => $rider->status,

                        'logistic_status' =>
                            $rider->logistic_status,

                        'logistic_id' =>
                            $rider->logistic_id !== null
                                ? (int) $rider->logistic_id
                                : null,

                        'hub_id' =>
                            $rider->hub_id !== null
                                ? (int) $rider->hub_id
                                : null,

                        'hub_name' =>
                            $rider->hub?->name,

                        'assigned_zone' =>
                            $rider->assigned_zone,

                        'vehicle_type' =>
                            $rider->vehicle_type,

                        'current_load' =>
                            (int) $rider->current_load,

                        'max_capacity' =>
                            (int) $rider->max_capacity,

                        'latitude' =>
                            $riderLatitude,

                        'longitude' =>
                            $riderLongitude,

                        'location_updated_at' =>
                            self::dateToIso8601(
                                $rider->location_updated_at
                            ),

                        'last_active_at' =>
                            self::dateToIso8601(
                                $rider->last_active_at
                            ),

                        'online' =>
                            self::isRecentlyOnline($rider),

                        'distance_km' =>
                            $distanceKm !== null
                                ? round($distanceKm, 2)
                                : null,
                    ];
                }
            )
            ->values();

        /*
         * Nearest rider first.
         *
         * Riders without GPS are placed after riders with
         * a calculated distance.
         *
         * Ties:
         * 1. lower current load
         * 2. lower rider ID
         */
        return $availableRiders
            ->sort(
                function (
                    array $a,
                    array $b
                ): int {
                    $distanceA = $a['distance_km'];
                    $distanceB = $b['distance_km'];

                    if (
                        $distanceA !== null &&
                        $distanceB === null
                    ) {
                        return -1;
                    }

                    if (
                        $distanceA === null &&
                        $distanceB !== null
                    ) {
                        return 1;
                    }

                    if (
                        $distanceA !== null &&
                        $distanceB !== null
                    ) {
                        $distanceComparison =
                            $distanceA <=> $distanceB;

                        if ($distanceComparison !== 0) {
                            return $distanceComparison;
                        }
                    }

                    $loadComparison =
                        $a['current_load']
                        <=>
                        $b['current_load'];

                    if ($loadComparison !== 0) {
                        return $loadComparison;
                    }

                    return $a['id'] <=> $b['id'];
                }
            )
            ->values();
    }

    /**
     * Find the best available final-mile rider.
     *
     * This now uses the same eligibility and ranking
     * rules as the Logistics available-rider list.
     */
    public static function findBestRiderForShipment(
        Shipment $shipment
    ): ?User {
        $candidate = self::eligibleRidersForShipment(
            $shipment
        )->first();

        if (!is_array($candidate)) {
            return null;
        }

        $riderId = (int) (
            $candidate['rider_id']
            ?? $candidate['id']
            ?? 0
        );

        if ($riderId <= 0) {
            return null;
        }

        return User::query()->find($riderId);
    }

    /**
     * Assign a final-mile rider exactly once per shipment.
     * Pickup rider assignment remains separate.
     */
    public static function assignRiderToShipment(
        Shipment $shipment,
        User $rider
    ): void {
        DB::transaction(
            function () use (
                $shipment,
                $rider
            ) {
                $lockedShipment = Shipment::query()
                    ->whereKey($shipment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedShipment->rider_id !== null) {
                    throw new RuntimeException(
                        'This shipment already has an assigned rider.'
                    );
                }

                if (
                    $lockedShipment->status
                        === 'failed_return_at_sorting_center' &&
                    (
                        $lockedShipment
                            ->failed_return_received_at
                            === null ||
                        !$lockedShipment
                            ->received_by_sorting_center
                    )
                ) {
                    throw new RuntimeException(
                        'Logistics must confirm receipt of the failed parcel before rider reassignment.'
                    );
                }

                if (
                    $lockedShipment->sorting_status !== 'staged' ||
                    !$lockedShipment->received_by_sorting_center
                ) {
                    throw new RuntimeException(
                        'Shipment must be received and staged at the sorting center before rider assignment.'
                    );
                }

                if ($lockedShipment->logistic_id === null) {
                    throw new RuntimeException(
                        'This shipment does not have a logistics company.'
                    );
                }

                if (
                    in_array(
                        $lockedShipment->status,
                        [
                            'delivered',
                            'cancelled',
                            'canceled',
                            'delivery_failed',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'This shipment cannot be assigned in its current status.'
                    );
                }

                $lockedShipment->loadMissing(
                    'sellerOrder.order'
                );

                if (
                    !$lockedShipment
                        ->sellerOrder
                        ?->order
                ) {
                    throw new RuntimeException(
                        'Shipment is not linked to a valid seller order.'
                    );
                }

                $isFailedReturn =
                    $lockedShipment->status
                        === 'failed_return_at_sorting_center' ||
                    $lockedShipment
                        ->failed_returned_to_sorting_center_at
                        !== null ||
                    $lockedShipment
                        ->failed_return_received_at
                        !== null;

                if ($isFailedReturn) {
                    if (
                        $lockedShipment->status
                            !== 'failed_return_at_sorting_center' ||
                        $lockedShipment
                            ->failed_return_received_at
                            === null ||
                        !$lockedShipment
                            ->received_by_sorting_center
                    ) {
                        throw new RuntimeException(
                            'Logistics must confirm receipt of the failed parcel before rider reassignment.'
                        );
                    }

                    if (
                        $lockedShipment
                            ->sellerOrder
                            ->reschedule_status
                        !== 'approved'
                    ) {
                        throw new RuntimeException(
                            'This failed-return shipment requires an approved seller-specific reschedule.'
                        );
                    }
                }

                $lockedRider = User::query()
                    ->whereKey($rider->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $isApprovedRider =
                    $lockedRider
                        ->roles()
                        ->where(
                            'name',
                            'rider'
                        )
                        ->exists();

                if (
                    !$isApprovedRider ||
                    $lockedRider->status
                        !== User::STATUS_ACTIVE ||
                    $lockedRider->logistic_status
                        !== 'approved'
                ) {
                    throw new RuntimeException(
                        'Only active and approved riders can be assigned to shipments.'
                    );
                }

                if (
                    $lockedRider->logistic_id === null ||
                    (int) $lockedRider->logistic_id
                        !==
                    (int) $lockedShipment->logistic_id
                ) {
                    throw new RuntimeException(
                        'Rider does not belong to this logistics company.'
                    );
                }

                /*
                 * Final-mile rider must belong to the
                 * destination barangay hub.
                 */
                $destinationHubId =
                    $lockedShipment->destination_hub_id
                    ?: $lockedShipment->hub_id;

                if (
                    !$destinationHubId ||
                    (int) $lockedRider->hub_id
                        !==
                    (int) $destinationHubId
                ) {
                    throw new RuntimeException(
                        'Delivery rider must belong to the shipment destination hub.'
                    );
                }

                /*
                 * Keep legacy delivery-zone validation
                 * only when both values exist.
                 *
                 * Hub membership is the primary dispatch
                 * boundary.
                 */
                $shipmentZone = mb_strtolower(
                    trim(
                        (string) $lockedShipment
                            ->delivery_zone
                    )
                );

                $riderZone = mb_strtolower(
                    trim(
                        (string) $lockedRider
                            ->assigned_zone
                    )
                );

                if (
                    $shipmentZone !== '' &&
                    $riderZone !== '' &&
                    $shipmentZone !== $riderZone
                ) {
                    throw new RuntimeException(
                        'Delivery rider is assigned to a different delivery zone.'
                    );
                }

                if (
                    $lockedRider->availability_status
                    !== 'available'
                ) {
                    throw new RuntimeException(
                        'Rider is currently unavailable.'
                    );
                }

                /*
                 * Require the rider to be recently active.
                 *
                 * This prevents Logistics from assigning
                 * riders who are marked available but have
                 * not recently updated their app activity
                 * or location.
                 */
                if (!self::isRecentlyOnline($lockedRider)) {
                    throw new RuntimeException(
                        'Rider is not currently online.'
                    );
                }

                $currentLoad =
                    (int) $lockedRider
                        ->current_load;

                $maxCapacity =
                    (int) $lockedRider
                        ->max_capacity;

                if (
                    $maxCapacity <= 0 ||
                    $currentLoad >= $maxCapacity
                ) {
                    throw new RuntimeException(
                        'Rider has reached maximum capacity.'
                    );
                }

                $shipmentStatus =
                    in_array(
                        $lockedShipment->status,
                        [
                            'at_sorting_center',
                            'failed_return_at_sorting_center',
                        ],
                        true
                    )
                        ? 'staged'
                        : 'assigned';

                /*
                 * Assign final-mile rider only.
                 */
                $lockedShipment->update([
                    'rider_id' =>
                        $lockedRider->id,

                    'status' =>
                        $shipmentStatus,
                ]);

                $newLoad =
                    $currentLoad + 1;

                $lockedRider->update([
                    'current_load' =>
                        $newLoad,

                    'availability_status' =>
                        $newLoad >= $maxCapacity
                            ? 'busy'
                            : 'available',
                ]);
            }
        );
    }

    /**
     * Check whether the shipment is currently eligible
     * for final-mile rider assignment.
     */
    private static function shipmentCanReceiveFinalMileAssignment(
        Shipment $shipment
    ): bool {
        if ($shipment->logistic_id === null) {
            return false;
        }

        if (
            $shipment->destination_hub_id === null &&
            $shipment->hub_id === null
        ) {
            return false;
        }

        if ($shipment->rider_id !== null) {
            return false;
        }

        if ($shipment->sorting_status !== 'staged') {
            return false;
        }

        if (!$shipment->received_by_sorting_center) {
            return false;
        }

        if (
            in_array(
                $shipment->status,
                [
                    'delivered',
                    'cancelled',
                    'canceled',
                    'delivery_failed',
                ],
                true
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the rider has been recently active.
     */
    private static function isRecentlyOnline(
        User $rider
    ): bool {
        $threshold = now()->subMinutes(
            self::ONLINE_WINDOW_MINUTES
        );

        $locationUpdatedAt =
            self::parseDate(
                $rider->location_updated_at
            );

        if (
            $locationUpdatedAt !== null &&
            $locationUpdatedAt->gte($threshold)
        ) {
            return true;
        }

        $lastActiveAt =
            self::parseDate(
                $rider->last_active_at
            );

        return $lastActiveAt !== null &&
            $lastActiveAt->gte($threshold);
    }

    /**
     * Haversine distance between two GPS points.
     */
    private static function distanceInKilometers(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB
    ): float {
        $earthRadiusKm = 6371.0088;

        $latitudeDelta =
            deg2rad(
                $latitudeB - $latitudeA
            );

        $longitudeDelta =
            deg2rad(
                $longitudeB - $longitudeA
            );

        $latitudeARadians =
            deg2rad($latitudeA);

        $latitudeBRadians =
            deg2rad($latitudeB);

        $a =
            sin($latitudeDelta / 2) ** 2 +
            cos($latitudeARadians) *
            cos($latitudeBRadians) *
            sin($longitudeDelta / 2) ** 2;

        $c =
            2 *
            atan2(
                sqrt($a),
                sqrt(1 - $a)
            );

        return $earthRadiusKm * $c;
    }

    /**
     * Convert a nullable numeric value to float.
     */
    private static function nullableFloat(
        mixed $value
    ): ?float {
        if ($value === null) {
            return null;
        }

        $normalized =
            trim((string) $value);

        if (
            $normalized === '' ||
            !is_numeric($normalized)
        ) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * Parse a nullable timestamp.
     */
    private static function parseDate(
        mixed $value
    ): ?\Illuminate\Support\Carbon {
        if ($value === null) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse(
                $value
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert timestamp to ISO-8601 for the API.
     */
    private static function dateToIso8601(
        mixed $value
    ): ?string {
        return self::parseDate($value)
            ?->toIso8601String();
    }
}