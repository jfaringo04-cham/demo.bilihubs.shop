<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HubTransferService
{
    public static function dispatch(Shipment $shipment): void
    {
        DB::transaction(function () use ($shipment) {
            $locked = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $originHubId = (int) (
                $locked->origin_hub_id ?: $locked->hub_id
            );

            $destinationHubId = (int) (
                $locked->destination_hub_id ?: $originHubId
            );

            if (!$originHubId || !$destinationHubId) {
                throw new RuntimeException(
                    'Shipment route is incomplete.'
                );
            }

            if ($originHubId === $destinationHubId) {
                throw new RuntimeException(
                    'This shipment does not require inter-hub transfer.'
                );
            }

            if (
                $locked->routing_status !== 'at_origin_hub' ||
                $locked->origin_hub_received_at === null
            ) {
                throw new RuntimeException(
                    'Parcel must first be received at the origin hub.'
                );
            }

            if ((int) $locked->hub_id !== $originHubId) {
                throw new RuntimeException(
                    'Parcel is not currently at its origin hub.'
                );
            }

            if ($locked->rider_id !== null) {
                throw new RuntimeException(
                    'Parcel cannot transfer while a final-mile rider is assigned.'
                );
            }

            if ($locked->linehaul_rider_id === null) {
                throw new RuntimeException(
                    'A line-haul driver must be assigned before hub transfer.'
                );
            }

            if (
                $locked->linehaul_status !==
                LineHaulAssignmentService::STATUS_ASSIGNED
            ) {
                throw new RuntimeException(
                    'Line-haul assignment is not ready for dispatch.'
                );
            }

            $driver = User::query()
                ->whereKey($locked->linehaul_rider_id)
                ->lockForUpdate()
                ->first();

            if (!$driver) {
                throw new RuntimeException(
                    'Assigned line-haul driver no longer exists.'
                );
            }

            $allowedVehicles =
                LineHaulAssignmentService::compatibleVehicles(
                    $locked->required_vehicle_type
                );

            $driverVehicle =
                LineHaulAssignmentService::normalizeVehicle(
                    $driver->vehicle_type
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
                    'Assigned driver vehicle no longer satisfies the shipment requirement.'
                );
            }

            $locked->update([
                'routing_status' => 'in_hub_transfer',
                'transfer_dispatched_at' => now(),
                'status' => 'in_transit',
                'sorting_status' => 'pending',
                'received_by_sorting_center' => false,
                'linehaul_status' =>
                LineHaulAssignmentService::STATUS_IN_TRANSIT,
                'linehaul_started_at' => now(),
            ]);
        });
    }

    public static function receiveAtDestination(
        Shipment $shipment
    ): void {
        DB::transaction(function () use ($shipment) {
            $locked = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $originHubId = (int) (
                $locked->origin_hub_id ?: $locked->hub_id
            );

            $destinationHubId = (int) (
                $locked->destination_hub_id ?: $originHubId
            );

            if (!$destinationHubId) {
                throw new RuntimeException(
                    'Shipment has no destination hub.'
                );
            }

            if (
                $locked->routing_status !== 'in_hub_transfer' ||
                $locked->transfer_dispatched_at === null
            ) {
                throw new RuntimeException(
                    'Parcel must be dispatched from the origin hub first.'
                );
            }

            if ($locked->destination_hub_received_at !== null) {
                throw new RuntimeException(
                    'Parcel has already been received at the destination hub.'
                );
            }

            if (
                $originHubId !== $destinationHubId &&
                (
                    $locked->linehaul_rider_id === null ||
                    $locked->linehaul_status !==
                    LineHaulAssignmentService::STATUS_IN_TRANSIT
                )
            ) {
                throw new RuntimeException(
                    'Parcel does not have an active line-haul transfer.'
                );
            }

            $now = now();

            $linehaulDriver = null;

            if ($locked->linehaul_rider_id !== null) {
                $linehaulDriver = User::query()
                    ->whereKey($locked->linehaul_rider_id)
                    ->lockForUpdate()
                    ->first();
            }

            $locked->update([
                'hub_id' => $destinationHubId,
                'routing_status' => 'at_destination_hub',
                'destination_hub_received_at' => $now,
                'status' => 'at_sorting_center',
                'sorting_status' => 'received',
                'received_at' => $now,
                'at_sorting_center_at' => $now,
                'received_by_sorting_center' => true,
                'rider_id' => null,
                'linehaul_status' =>
                LineHaulAssignmentService::STATUS_COMPLETED,
                'linehaul_completed_at' => $now,
            ]);

            if ($linehaulDriver) {
                $newLoad = max(
                    0,
                    (int) $linehaulDriver->current_load - 1
                );

                $linehaulDriver->update([
                    'current_load' => $newLoad,
                    'availability_status' => 'available',
                ]);
            }
        });
    }

    public static function ensureAtDestinationForSorting(
        Shipment $shipment
    ): void {
        $originHubId = (int) (
            $shipment->origin_hub_id ?: $shipment->hub_id
        );

        $destinationHubId = (int) (
            $shipment->destination_hub_id ?: $originHubId
        );

        if (!$destinationHubId) {
            throw new RuntimeException(
                'Shipment has no destination hub.'
            );
        }

        if (
            $originHubId !== $destinationHubId &&
            (
                $shipment->routing_status !==
                'at_destination_hub' ||
                $shipment->destination_hub_received_at === null ||
                (int) $shipment->hub_id !== $destinationHubId ||
                $shipment->linehaul_status !==
                LineHaulAssignmentService::STATUS_COMPLETED
            )
        ) {
            throw new RuntimeException(
                'Parcel must complete its line-haul transfer and arrive at the destination hub before sorting or final-mile assignment.'
            );
        }
    }
}
