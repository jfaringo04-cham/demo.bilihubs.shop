<?php

namespace App\Services;

use App\Models\LinehaulTrip;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LinehaulTripService
{
    public static function create(
        int $logisticId,
        int $originHubId,
        int $destinationHubId,
        int $driverId,
        int $minimumLoad,
        string $scheduledDepartureAt
    ): LinehaulTrip {
        return DB::transaction(function () use (
            $logisticId,
            $originHubId,
            $destinationHubId,
            $driverId,
            $minimumLoad,
            $scheduledDepartureAt
        ) {
            if ($originHubId === $destinationHubId) {
                throw new RuntimeException(
                    'Origin and destination hubs must be different.'
                );
            }

            $existingLoadingTrip = LinehaulTrip::query()
                ->where('logistic_id', $logisticId)
                ->where('origin_hub_id', $originHubId)
                ->where('destination_hub_id', $destinationHubId)
                ->where('status', LinehaulTrip::STATUS_LOADING)
                ->exists();

            if ($existingLoadingTrip) {
                throw new RuntimeException(
                    'There is already a loading truck trip for this route.'
                );
            }

            $driver = User::query()
                ->whereKey($driverId)
                ->lockForUpdate()
                ->firstOrFail();

            self::validateDriver(
                $driver,
                $logisticId,
                $originHubId
            );

            $remainingCapacity =
                (int) $driver->max_capacity
                - (int) $driver->current_load;

            if ($remainingCapacity <= 0) {
                throw new RuntimeException(
                    'The selected truck has no remaining capacity.'
                );
            }

            if ($minimumLoad < 1) {
                throw new RuntimeException(
                    'Minimum load must be at least one parcel.'
                );
            }

            if ($minimumLoad > $remainingCapacity) {
                throw new RuntimeException(
                    'Minimum load cannot exceed the truck capacity.'
                );
            }

            $trip = LinehaulTrip::create([
                'logistic_id' => $logisticId,
                'origin_hub_id' => $originHubId,
                'destination_hub_id' => $destinationHubId,
                'driver_id' => $driver->id,
                'trip_number' => self::generateTripNumber(),
                'status' => LinehaulTrip::STATUS_LOADING,
                'minimum_load' => $minimumLoad,
                'capacity_limit' => $remainingCapacity,
                'scheduled_departure_at' =>
                    $scheduledDepartureAt,
            ]);

            /*
             * Automatically load every currently eligible parcel
             * for this route, up to truck capacity.
             */
            self::loadAvailableParcels($trip);

            return $trip->fresh([
                'originHub',
                'destinationHub',
                'driver',
                'shipments',
            ]);
        }, 3);
    }

    public static function loadAvailableParcels(
        LinehaulTrip $trip
    ): int {
        return DB::transaction(function () use ($trip) {
            $trip = LinehaulTrip::query()
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($trip->status !== LinehaulTrip::STATUS_LOADING) {
                throw new RuntimeException(
                    'Parcels can only be added while the truck is loading.'
                );
            }

            $driver = User::query()
                ->whereKey($trip->driver_id)
                ->lockForUpdate()
                ->firstOrFail();

            self::validateDriver(
                $driver,
                (int) $trip->logistic_id,
                (int) $trip->origin_hub_id,
                false
            );

            $tripParcelCount = $trip->shipments()->count();

            $tripRemaining =
                (int) $trip->capacity_limit
                - $tripParcelCount;

            $driverRemaining =
                (int) $driver->max_capacity
                - (int) $driver->current_load;

            $availableSlots = min(
                $tripRemaining,
                $driverRemaining
            );

            if ($availableSlots <= 0) {
                return 0;
            }

            $alreadyManifestedShipmentIds = DB::table(
                'linehaul_trip_shipments'
            )->pluck('shipment_id');

            $shipments = Shipment::query()
                ->where('logistic_id', $trip->logistic_id)
                ->where('origin_hub_id', $trip->origin_hub_id)
                ->where(
                    'destination_hub_id',
                    $trip->destination_hub_id
                )
                ->where('hub_id', $trip->origin_hub_id)
                ->where(
                    'routing_status',
                    'at_origin_hub'
                )
                ->whereNotNull('origin_hub_received_at')
                ->whereNull('rider_id')
                ->whereNull('linehaul_rider_id')
                ->whereNotIn(
                    'status',
                    [
                        'delivered',
                        'cancelled',
                        'canceled',
                        'delivery_failed',
                    ]
                )
                ->when(
                    $alreadyManifestedShipmentIds->isNotEmpty(),
                    fn ($query) =>
                        $query->whereNotIn(
                            'id',
                            $alreadyManifestedShipmentIds
                        )
                )
                ->orderBy('origin_hub_received_at')
                ->orderBy('id')
                ->limit($availableSlots)
                ->lockForUpdate()
                ->get();

            $loaded = 0;

            foreach ($shipments as $shipment) {
                /*
                 * Existing service remains responsible for:
                 * - truck validation
                 * - rider/logistics validation
                 * - origin hub validation
                 * - linehaul status
                 * - driver capacity
                 */
                LineHaulAssignmentService::assign(
                    $shipment,
                    $driver
                );

                $trip->shipments()->attach(
                    $shipment->id
                );

                $loaded++;
            }

            return $loaded;
        }, 3);
    }

    public static function dispatch(
        LinehaulTrip $trip
    ): void {
        DB::transaction(function () use ($trip) {
            $trip = LinehaulTrip::query()
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($trip->status !== LinehaulTrip::STATUS_LOADING) {
                throw new RuntimeException(
                    'Only a loading truck trip can be dispatched.'
                );
            }

            /*
             * Before dispatching, make one final attempt to
             * load parcels that arrived while the truck waited.
             */
            self::loadAvailableParcels($trip);

            $trip = $trip->fresh();

            if (!$trip->canDispatch()) {
                throw new RuntimeException(
                    'Truck is still waiting. Minimum load has not '
                    . 'been reached and the scheduled departure '
                    . 'time has not arrived yet.'
                );
            }

            $shipments = $trip->shipments()
                ->orderBy('shipments.id')
                ->lockForUpdate()
                ->get();

            if ($shipments->isEmpty()) {
                throw new RuntimeException(
                    'Cannot dispatch a truck with no parcels.'
                );
            }

            foreach ($shipments as $shipment) {
                if (
                    (int) $shipment->linehaul_rider_id
                    !== (int) $trip->driver_id
                ) {
                    throw new RuntimeException(
                        'A parcel is no longer assigned '
                        . 'to this truck driver.'
                    );
                }

                if (
                    (int) $shipment->origin_hub_id
                    !== (int) $trip->origin_hub_id
                    ||
                    (int) $shipment->destination_hub_id
                    !== (int) $trip->destination_hub_id
                ) {
                    throw new RuntimeException(
                        'A parcel route no longer matches this trip.'
                    );
                }
            }

            foreach ($shipments as $shipment) {
                /*
                 * Preserve the existing shipment transition.
                 */
                HubTransferService::dispatch(
                    $shipment
                );
            }

            $trip->update([
                'status' => LinehaulTrip::STATUS_DISPATCHED,
                'dispatched_at' => now(),
            ]);
        }, 3);
    }

    public static function receive(
        LinehaulTrip $trip
    ): void {
        DB::transaction(function () use ($trip) {
            $trip = LinehaulTrip::query()
                ->whereKey($trip->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $trip->status
                !== LinehaulTrip::STATUS_DISPATCHED
            ) {
                throw new RuntimeException(
                    'Only a dispatched truck can be received.'
                );
            }

            $shipments = $trip->shipments()
                ->orderBy('shipments.id')
                ->lockForUpdate()
                ->get();

            if ($shipments->isEmpty()) {
                throw new RuntimeException(
                    'This truck trip contains no parcels.'
                );
            }

            foreach ($shipments as $shipment) {
                HubTransferService::receiveAtDestination(
                    $shipment
                );
            }

            $trip->update([
                'status' => LinehaulTrip::STATUS_ARRIVED,
                'arrived_at' => now(),
            ]);
        }, 3);
    }

    private static function validateDriver(
        User $driver,
        int $logisticId,
        int $originHubId,
        bool $requireAvailable = true
    ): void {
        if (
            !$driver->roles()
                ->where('name', 'rider')
                ->exists()
        ) {
            throw new RuntimeException(
                'Selected truck driver is not a rider.'
            );
        }

        if (
            (int) $driver->logistic_id
            !== $logisticId
        ) {
            throw new RuntimeException(
                'Truck driver does not belong '
                . 'to this logistics company.'
            );
        }

        if (
            (int) $driver->hub_id
            !== $originHubId
        ) {
            throw new RuntimeException(
                'Truck driver must belong to the origin hub.'
            );
        }

        if (
            strtolower(
                trim((string) $driver->vehicle_type)
            ) !== 'truck'
        ) {
            throw new RuntimeException(
                'Only a truck rider can handle '
                . 'an inter-hub truck trip.'
            );
        }

        if ($driver->logistic_status !== 'approved') {
            throw new RuntimeException(
                'Truck driver is not approved.'
            );
        }

        if ($driver->status !== User::STATUS_ACTIVE) {
            throw new RuntimeException(
                'Truck driver is not active.'
            );
        }

        if (
            $requireAvailable
            && $driver->availability_status !== 'available'
        ) {
            throw new RuntimeException(
                'Truck driver is not currently available.'
            );
        }

        if ((int) $driver->max_capacity <= 0) {
            throw new RuntimeException(
                'Truck driver has invalid capacity.'
            );
        }
    }

    private static function generateTripNumber(): string
    {
        do {
            $tripNumber =
                'LH-'
                . now()->format('Ymd')
                . '-'
                . strtoupper(
                    bin2hex(random_bytes(3))
                );
        } while (
            LinehaulTrip::query()
                ->where(
                    'trip_number',
                    $tripNumber
                )
                ->exists()
        );

        return $tripNumber;
    }
}