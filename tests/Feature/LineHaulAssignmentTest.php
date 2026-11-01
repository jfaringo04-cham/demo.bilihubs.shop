<?php

namespace Tests\Feature;

use App\Models\Hub;
use App\Models\Logistic;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\HubTransferService;
use App\Services\LineHaulAssignmentService;
use App\Models\LinehaulTrip;
use App\Services\LinehaulTripService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LineHaulAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function createLogistic(): Logistic
    {
        return Logistic::factory()->create([
            'status' => 'active',
        ]);
    }

    private function createHub(
        Logistic $logistic,
        string $name,
        float $latitude,
        float $longitude
    ): Hub {
        return Hub::create([
            'logistic_id' => $logistic->id,
            'name' => $name,
            'address' => $name . ' Address',
            'city' => $name,
            'province' => 'Laguna',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => 'active',
            'is_default' => false,
        ]);
    }

    private function createRider(
        Logistic $logistic,
        Hub $hub,
        string $vehicleType,
        int $currentLoad = 0,
        int $maxCapacity = 10
    ): User {
        $rider = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'logistic_id' => $logistic->id,
            'hub_id' => $hub->id,
            'logistic_status' => 'approved',
            'vehicle_type' => $vehicleType,
            'availability_status' => 'available',
            'current_load' => $currentLoad,
            'max_capacity' => $maxCapacity,
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'rider',
        ]);

        $rider->roles()->syncWithoutDetaching([
            $role->id,
        ]);

        return $rider;
    }

    private function createSellerOrder(): int
    {
        $sellerOwner = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $buyer = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $sellerId = DB::table('sellers')->insertGetId([
            'user_id' => $sellerOwner->id,
            'name' => 'Linehaul Test Seller',
            'slug' => 'linehaul-test-seller-' . $sellerOwner->id,
            'status' => 'approved',
            'commission_bps' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $buyer->id,
            'order_number' => 'LINEHAUL-' . uniqid(),
            'shipping_address' => 'Calamba, Laguna',
            'status' => 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal_minor' => 100000,
            'shipping_minor' => 5000,
            'total_minor' => 105000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId,
            'seller_id' => $sellerId,
            'status' => 'processing',
            'subtotal_minor' => 100000,
            'shipping_minor' => 5000,
            'total_minor' => 105000,
            'return_status' => 'none',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTruckShipment(
        Logistic $logistic,
        Hub $originHub,
        Hub $destinationHub
    ): Shipment {
        return Shipment::create([
            'seller_order_id' => $this->createSellerOrder(),
            'logistic_id' => $logistic->id,
            'hub_id' => $originHub->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destinationHub->id,

            'routing_status' => 'at_origin_hub',

            'required_vehicle_type' => 'truck',
            'parcel_weight_kg' => 120,
            'estimated_distance_km' => 45,
            'linehaul_status' =>
            LineHaulAssignmentService::STATUS_AWAITING_ASSIGNMENT,

            'tracking_number' => 'LH-' . strtoupper(uniqid()),
            'tracking_code' => 'LHC-' . strtoupper(uniqid()),

            'status' => 'at_sorting_center',
            'sorting_status' => 'received',
            'received_by_sorting_center' => true,

            'pickup_address' => 'Santa Cruz, Laguna',
            'delivery_address' => 'Calamba, Laguna',

            'origin_hub_received_at' => now(),
            'received_at' => now(),
            'at_sorting_center_at' => now(),
        ]);
    }

    public function test_motorcycle_cannot_take_truck_shipment(): void
    {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $shipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $motorcycleRider = $this->createRider(
            $logistic,
            $origin,
            'motorcycle'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver vehicle does not meet the required vehicle type for this shipment.'
        );

        LineHaulAssignmentService::assign(
            $shipment,
            $motorcycleRider
        );
    }

    public function test_van_cannot_take_truck_shipment(): void
    {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $shipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $vanRider = $this->createRider(
            $logistic,
            $origin,
            'van'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver vehicle does not meet the required vehicle type for this shipment.'
        );

        LineHaulAssignmentService::assign(
            $shipment,
            $vanRider
        );
    }

    public function test_truck_driver_can_be_assigned(): void
    {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $shipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $truckDriver = $this->createRider(
            $logistic,
            $origin,
            'truck'
        );

        LineHaulAssignmentService::assign(
            $shipment,
            $truckDriver
        );

        $shipment->refresh();
        $truckDriver->refresh();

        $this->assertSame(
            $truckDriver->id,
            $shipment->linehaul_rider_id
        );

        $this->assertSame(
            LineHaulAssignmentService::STATUS_ASSIGNED,
            $shipment->linehaul_status
        );

        $this->assertNotNull(
            $shipment->linehaul_assigned_at
        );

        $this->assertSame(
            1,
            (int) $truckDriver->current_load
        );
    }

    public function test_driver_from_other_logistics_cannot_be_assigned(): void
    {
        $shipmentLogistic = $this->createLogistic();
        $otherLogistic = $this->createLogistic();

        $origin = $this->createHub(
            $shipmentLogistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $shipmentLogistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $otherHub = $this->createHub(
            $otherLogistic,
            'Other Hub',
            14.3000,
            121.4000
        );

        $shipment = $this->createTruckShipment(
            $shipmentLogistic,
            $origin,
            $destination
        );

        $driver = $this->createRider(
            $otherLogistic,
            $otherHub,
            'truck'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Line-haul driver must belong to the shipment logistics company.'
        );

        LineHaulAssignmentService::assign(
            $shipment,
            $driver
        );
    }

    public function test_driver_must_belong_to_origin_hub(): void
    {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $wrongHub = $this->createHub(
            $logistic,
            'Wrong Hub',
            14.3500,
            121.4500
        );

        $shipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $driver = $this->createRider(
            $logistic,
            $wrongHub,
            'truck'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Line-haul driver must belong to the shipment origin hub.'
        );

        LineHaulAssignmentService::assign(
            $shipment,
            $driver
        );
    }

    public function test_cannot_dispatch_without_linehaul_driver(): void
    {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $shipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'A line-haul driver must be assigned before hub transfer.'
        );

        HubTransferService::dispatch($shipment);
    }

    public function test_dispatch_changes_linehaul_to_in_transit(): void
    {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $shipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $driver = $this->createRider(
            $logistic,
            $origin,
            'truck'
        );

        LineHaulAssignmentService::assign(
            $shipment,
            $driver
        );

        $shipment->refresh();

        HubTransferService::dispatch($shipment);

        $shipment->refresh();

        $this->assertSame(
            'in_hub_transfer',
            $shipment->routing_status
        );

        $this->assertSame(
            LineHaulAssignmentService::STATUS_IN_TRANSIT,
            $shipment->linehaul_status
        );

        $this->assertSame(
            'in_transit',
            $shipment->status
        );

        $this->assertNotNull(
            $shipment->transfer_dispatched_at
        );

        $this->assertNotNull(
            $shipment->linehaul_started_at
        );
    }

    public function test_destination_receipt_completes_linehaul_and_releases_driver_load(): void
    {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $shipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $driver = $this->createRider(
            $logistic,
            $origin,
            'truck'
        );

        LineHaulAssignmentService::assign(
            $shipment,
            $driver
        );

        $shipment->refresh();

        HubTransferService::dispatch($shipment);

        $shipment->refresh();

        HubTransferService::receiveAtDestination(
            $shipment
        );

        $shipment->refresh();
        $driver->refresh();

        $this->assertSame(
            $destination->id,
            $shipment->hub_id
        );

        $this->assertSame(
            'at_destination_hub',
            $shipment->routing_status
        );

        $this->assertSame(
            LineHaulAssignmentService::STATUS_COMPLETED,
            $shipment->linehaul_status
        );

        $this->assertSame(
            'at_sorting_center',
            $shipment->status
        );

        $this->assertSame(
            'received',
            $shipment->sorting_status
        );

        $this->assertNotNull(
            $shipment->destination_hub_received_at
        );

        $this->assertNotNull(
            $shipment->linehaul_completed_at
        );

        $this->assertSame(
            0,
            (int) $driver->current_load
        );

        $this->assertSame(
            'available',
            $driver->availability_status
        );
    }

    public function test_vehicle_requirement_rules(): void
    {
        $this->assertSame(
            'motorcycle',
            LineHaulAssignmentService::determineRequiredVehicle(
                5,
                2
            )
        );

        $this->assertSame(
            'car',
            LineHaulAssignmentService::determineRequiredVehicle(
                12,
                5
            )
        );

        $this->assertSame(
            'van',
            LineHaulAssignmentService::determineRequiredVehicle(
                25,
                15
            )
        );

        $this->assertSame(
            'truck',
            LineHaulAssignmentService::determineRequiredVehicle(
                45,
                120
            )
        );

        $this->assertSame(
            'truck',
            LineHaulAssignmentService::determineRequiredVehicle(
                70,
                5
            )
        );
    }

    private function createManifestRoute(
        int $shipmentCount = 1,
        int $truckCapacity = 10
    ): array {
        $logistic = $this->createLogistic();

        $origin = $this->createHub(
            $logistic,
            'Santa Cruz Hub',
            14.2814,
            121.4161
        );

        $destination = $this->createHub(
            $logistic,
            'Calamba Hub',
            14.2117,
            121.1653
        );

        $shipments = collect();

        for ($i = 0; $i < $shipmentCount; $i++) {
            $shipments->push(
                $this->createTruckShipment(
                    $logistic,
                    $origin,
                    $destination
                )
            );
        }

        $driver = $this->createRider(
            $logistic,
            $origin,
            'truck',
            0,
            $truckCapacity
        );

        return [
            $logistic,
            $origin,
            $destination,
            $driver,
            $shipments,
        ];
    }

    public function test_manifest_groups_parcels_with_same_route(): void
    {
        [
            $logistic,
            $origin,
            $destination,
            $driver,
            $shipments,
        ] = $this->createManifestRoute(2);

        $trip = LinehaulTripService::create(
            $logistic->id,
            $origin->id,
            $destination->id,
            $driver->id,
            5,
            now()->addHour()->toDateTimeString()
        );

        $this->assertSame(
            LinehaulTrip::STATUS_LOADING,
            $trip->status
        );

        $this->assertSame(
            2,
            $trip->shipments()->count()
        );

        foreach ($shipments as $shipment) {
            $this->assertTrue(
                $trip->shipments()
                    ->whereKey($shipment->id)
                    ->exists()
            );
        }
    }

    public function test_manifest_waits_before_minimum_load_and_schedule(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-11-01 08:00:00')
        );

        try {
            [
                $logistic,
                $origin,
                $destination,
                $driver,
            ] = $this->createManifestRoute(1);

            $trip = LinehaulTripService::create(
                $logistic->id,
                $origin->id,
                $destination->id,
                $driver->id,
                5,
                '2026-11-01 10:00:00'
            )->fresh();

            $this->assertSame(
                1,
                $trip->shipments()->count()
            );

            $this->assertFalse(
                $trip->canDispatch()
            );

            $this->assertSame(
                'Waiting for more parcels or departure time',
                $trip->readinessReason()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manifest_minimum_load_allows_early_dispatch(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-11-01 08:00:00')
        );

        try {
            [
                $logistic,
                $origin,
                $destination,
                $driver,
            ] = $this->createManifestRoute(2);

            $trip = LinehaulTripService::create(
                $logistic->id,
                $origin->id,
                $destination->id,
                $driver->id,
                2,
                '2026-11-01 10:00:00'
            )->fresh();

            $this->assertSame(
                2,
                $trip->shipments()->count()
            );

            $this->assertTrue(
                $trip->canDispatch()
            );

            $this->assertSame(
                'Minimum load reached',
                $trip->readinessReason()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manifest_schedule_allows_dispatch_below_minimum(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-11-01 10:00:00')
        );

        try {
            [
                $logistic,
                $origin,
                $destination,
                $driver,
            ] = $this->createManifestRoute(1);

            $trip = LinehaulTripService::create(
                $logistic->id,
                $origin->id,
                $destination->id,
                $driver->id,
                10,
                '2026-11-01 10:00:00'
            )->fresh();

            $this->assertSame(
                1,
                $trip->shipments()->count()
            );

            $this->assertTrue(
                $trip->canDispatch()
            );

            $this->assertSame(
                'Scheduled departure reached',
                $trip->readinessReason()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_full_truck_is_ready_for_dispatch(): void
    {
        [
            $logistic,
            $origin,
            $destination,
            $driver,
        ] = $this->createManifestRoute(
            shipmentCount: 2,
            truckCapacity: 2
        );

        $trip = LinehaulTripService::create(
            $logistic->id,
            $origin->id,
            $destination->id,
            $driver->id,
            2,
            now()->addHours(2)->toDateTimeString()
        )->fresh();

        $driver->refresh();

        $this->assertSame(
            2,
            $trip->shipments()->count()
        );

        $this->assertSame(
            2,
            (int) $driver->current_load
        );

        $this->assertTrue(
            $trip->isFull()
        );

        $this->assertTrue(
            $trip->canDispatch()
        );

        $this->assertSame(
            'Truck capacity reached',
            $trip->readinessReason()
        );
    }

    public function test_new_same_route_parcel_can_join_waiting_manifest(): void
    {
        [
            $logistic,
            $origin,
            $destination,
            $driver,
        ] = $this->createManifestRoute(1);

        $trip = LinehaulTripService::create(
            $logistic->id,
            $origin->id,
            $destination->id,
            $driver->id,
            5,
            now()->addHour()->toDateTimeString()
        );

        $this->assertSame(
            1,
            $trip->shipments()->count()
        );

        $newShipment = $this->createTruckShipment(
            $logistic,
            $origin,
            $destination
        );

        $loaded =
            LinehaulTripService::loadAvailableParcels(
                $trip
            );

        $this->assertSame(
            1,
            $loaded
        );

        $this->assertSame(
            2,
            $trip->shipments()->count()
        );

        $this->assertTrue(
            $trip->shipments()
                ->whereKey($newShipment->id)
                ->exists()
        );
    }

    public function test_manifest_does_not_load_parcel_for_another_destination(): void
    {
        [
            $logistic,
            $origin,
            $calamba,
            $driver,
            $shipments,
        ] = $this->createManifestRoute(1);

        $staRosa = $this->createHub(
            $logistic,
            'Santa Rosa Hub',
            14.3122,
            121.1114
        );

        $otherRouteShipment =
            $this->createTruckShipment(
                $logistic,
                $origin,
                $staRosa
            );

        $trip = LinehaulTripService::create(
            $logistic->id,
            $origin->id,
            $calamba->id,
            $driver->id,
            1,
            now()->addHour()->toDateTimeString()
        );

        $calambaShipment = $shipments->first();

        $this->assertTrue(
            $trip->shipments()
                ->whereKey($calambaShipment->id)
                ->exists()
        );

        $this->assertFalse(
            $trip->shipments()
                ->whereKey($otherRouteShipment->id)
                ->exists()
        );

        $this->assertSame(
            1,
            $trip->shipments()->count()
        );
    }

    public function test_manifest_cannot_dispatch_too_early(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-11-01 08:00:00')
        );

        try {
            [
                $logistic,
                $origin,
                $destination,
                $driver,
            ] = $this->createManifestRoute(1);

            $trip = LinehaulTripService::create(
                $logistic->id,
                $origin->id,
                $destination->id,
                $driver->id,
                5,
                '2026-11-01 10:00:00'
            );

            $this->expectException(
                RuntimeException::class
            );

            $this->expectExceptionMessage(
                'Truck is still waiting.'
            );

            LinehaulTripService::dispatch(
                $trip
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manifest_dispatch_updates_every_loaded_parcel(): void
    {
        [
            $logistic,
            $origin,
            $destination,
            $driver,
            $shipments,
        ] = $this->createManifestRoute(2);

        $trip = LinehaulTripService::create(
            $logistic->id,
            $origin->id,
            $destination->id,
            $driver->id,
            2,
            now()->addHour()->toDateTimeString()
        );

        LinehaulTripService::dispatch(
            $trip
        );

        $trip->refresh();

        $this->assertSame(
            LinehaulTrip::STATUS_DISPATCHED,
            $trip->status
        );

        $this->assertNotNull(
            $trip->dispatched_at
        );

        foreach ($shipments as $shipment) {
            $shipment->refresh();

            $this->assertSame(
                'in_hub_transfer',
                $shipment->routing_status
            );

            $this->assertSame(
                LineHaulAssignmentService::STATUS_IN_TRANSIT,
                $shipment->linehaul_status
            );

            $this->assertSame(
                'in_transit',
                $shipment->status
            );

            $this->assertNotNull(
                $shipment->transfer_dispatched_at
            );

            $this->assertNotNull(
                $shipment->linehaul_started_at
            );
        }
    }

    public function test_manifest_receive_completes_every_parcel_and_releases_capacity(): void
    {
        [
            $logistic,
            $origin,
            $destination,
            $driver,
            $shipments,
        ] = $this->createManifestRoute(2);

        $initialLoad =
            (int) $driver->current_load;

        $trip = LinehaulTripService::create(
            $logistic->id,
            $origin->id,
            $destination->id,
            $driver->id,
            2,
            now()->addHour()->toDateTimeString()
        );

        $driver->refresh();

        $this->assertSame(
            $initialLoad + 2,
            (int) $driver->current_load
        );

        LinehaulTripService::dispatch(
            $trip
        );

        LinehaulTripService::receive(
            $trip
        );

        $trip->refresh();
        $driver->refresh();

        $this->assertSame(
            LinehaulTrip::STATUS_ARRIVED,
            $trip->status
        );

        $this->assertNotNull(
            $trip->arrived_at
        );

        foreach ($shipments as $shipment) {
            $shipment->refresh();

            $this->assertSame(
                $destination->id,
                (int) $shipment->hub_id
            );

            $this->assertSame(
                'at_destination_hub',
                $shipment->routing_status
            );

            $this->assertSame(
                LineHaulAssignmentService::STATUS_COMPLETED,
                $shipment->linehaul_status
            );

            $this->assertSame(
                'at_sorting_center',
                $shipment->status
            );

            $this->assertSame(
                'received',
                $shipment->sorting_status
            );

            $this->assertNotNull(
                $shipment->destination_hub_received_at
            );

            $this->assertNotNull(
                $shipment->linehaul_completed_at
            );
        }

        $this->assertSame(
            $initialLoad,
            (int) $driver->current_load
        );

        $this->assertSame(
            'available',
            $driver->availability_status
        );
    }
}

