<?php

namespace Tests\Feature;

use App\Models\Hub;
use App\Models\Logistic;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Services\HubTransferService;
use App\Services\LineHaulAssignmentService;
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
}
