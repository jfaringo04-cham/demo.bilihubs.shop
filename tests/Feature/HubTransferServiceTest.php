<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Services\HubTransferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class HubTransferServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        app('db')->purge('sqlite');
        app('db')->reconnect('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();

            $table->string('vehicle_type')->nullable();

            $table->unsignedInteger('max_capacity')
                ->default(10);

            $table->unsignedInteger('current_load')
                ->default(0);

            $table->string('availability_status')
                ->default('available');

            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('hub_id')->nullable();

            $table->unsignedBigInteger('origin_hub_id')->nullable();

            $table->unsignedBigInteger('destination_hub_id')->nullable();

            $table->unsignedBigInteger('linehaul_rider_id')->nullable();

            $table->string('required_vehicle_type', 30)
                ->nullable();

            $table->decimal('parcel_weight_kg', 10, 2)
                ->nullable();

            $table->decimal('estimated_distance_km', 10, 2)
                ->nullable();

            $table->string('linehaul_status', 40)
                ->default('not_required');

            $table->timestamp('linehaul_assigned_at')
                ->nullable();

            $table->timestamp('linehaul_started_at')
                ->nullable();

            $table->timestamp('linehaul_completed_at')
                ->nullable();

            $table->string('routing_status')
                ->default('awaiting_pickup');

            $table->timestamp('origin_hub_received_at')
                ->nullable();

            $table->timestamp('transfer_dispatched_at')
                ->nullable();

            $table->timestamp('destination_hub_received_at')
                ->nullable();

            $table->string('status')
                ->default('pending');

            $table->string('sorting_status')
                ->default('pending');

            $table->boolean('received_by_sorting_center')
                ->default(false);

            $table->string('qr_token')->nullable();

            $table->timestamp('received_at')->nullable();

            $table->timestamp('at_sorting_center_at')
                ->nullable();

            $table->unsignedBigInteger('rider_id')->nullable();

            $table->timestamps();
        });
    }

    private function truckDriver(
        int $currentLoad = 0
    ): int {
        return DB::table('users')->insertGetId([
            'name' => 'Test Truck Driver',
            'email' => 'truck-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'vehicle_type' => 'truck',
            'max_capacity' => 10,
            'current_load' => $currentLoad,
            'availability_status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function shipmentAtOrigin(
        ?int $driverId = null
    ): Shipment {
        return Shipment::create([
            'hub_id' => 10,
            'origin_hub_id' => 10,
            'destination_hub_id' => 20,

            'routing_status' => 'at_origin_hub',

            'origin_hub_received_at' => now(),

            'required_vehicle_type' => 'truck',
            'parcel_weight_kg' => 120,
            'estimated_distance_km' => 45,

            'linehaul_rider_id' => $driverId,

            'linehaul_status' => $driverId
                ? 'assigned'
                : 'awaiting_assignment',

            'linehaul_assigned_at' => $driverId
                ? now()
                : null,

            'status' => 'at_sorting_center',

            'sorting_status' => 'pending',

            'received_by_sorting_center' => false,
        ]);
    }

    public function test_parcel_moves_from_origin_to_destination_hub(): void
    {
        $driverId = $this->truckDriver(1);

        $shipment = $this->shipmentAtOrigin(
            $driverId
        );

        HubTransferService::dispatch($shipment);

        $shipment->refresh();

        $this->assertSame(
            'in_hub_transfer',
            $shipment->routing_status
        );

        $this->assertSame(
            'in_transit',
            $shipment->status
        );

        $this->assertSame(
            'in_transit',
            $shipment->linehaul_status
        );

        $this->assertNotNull(
            $shipment->transfer_dispatched_at
        );

        $this->assertNotNull(
            $shipment->linehaul_started_at
        );

        $this->assertFalse(
            $shipment->received_by_sorting_center
        );

        HubTransferService::receiveAtDestination(
            $shipment
        );

        $shipment->refresh();

        $this->assertSame(
            20,
            (int) $shipment->hub_id
        );

        $this->assertSame(
            'at_destination_hub',
            $shipment->routing_status
        );

        $this->assertSame(
            'at_sorting_center',
            $shipment->status
        );

        $this->assertSame(
            'received',
            $shipment->sorting_status
        );

        $this->assertSame(
            'completed',
            $shipment->linehaul_status
        );

        $this->assertTrue(
            $shipment->received_by_sorting_center
        );

        $this->assertNotNull(
            $shipment->destination_hub_received_at
        );

        $this->assertNotNull(
            $shipment->linehaul_completed_at
        );

        $this->assertDatabaseHas('users', [
            'id' => $driverId,
            'current_load' => 0,
            'availability_status' => 'available',
        ]);
    }

    public function test_cannot_receive_destination_before_dispatch(): void
    {
        $driverId = $this->truckDriver(1);

        $shipment = $this->shipmentAtOrigin(
            $driverId
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Parcel must be dispatched from the origin hub first.'
        );

        HubTransferService::receiveAtDestination(
            $shipment
        );
    }

    public function test_sorting_is_blocked_until_destination_arrival(): void
    {
        $driverId = $this->truckDriver(1);

        $shipment = $this->shipmentAtOrigin(
            $driverId
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Parcel must complete its line-haul transfer and arrive at the destination hub before sorting or final-mile assignment.'
        );

        HubTransferService::ensureAtDestinationForSorting(
            $shipment
        );
    }
}
