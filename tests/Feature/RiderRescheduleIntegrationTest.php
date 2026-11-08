<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderAssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\SQLiteTestCase;

class RiderRescheduleIntegrationTest extends SQLiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            !app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            DB::connection()->getDriverName() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new RuntimeException('RiderRescheduleIntegrationTest requires SQLite :memory: testing.');
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('logistic_status')->nullable();
            $table->unsignedBigInteger('logistic_id')->nullable();
            $table->unsignedBigInteger('hub_id')->nullable();
            $table->string('assigned_zone')->nullable();
            $table->string('availability_status')->default('available');
            $table->integer('current_load')->default(0);
            $table->integer('max_capacity')->default(5);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['role_id', 'user_id']);
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('order_number')->unique();
            $table->string('status')->default('placed');
            $table->timestamps();
        });
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id');
            $table->string('status')->default('delivery_failed');
            $table->string('reschedule_status')->default('none');
            $table->timestamps();
        });
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_id')->nullable();
            $table->unsignedBigInteger('hub_id')->nullable();
            $table->string('delivery_zone')->nullable();
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->string('tracking_number')->unique();
            $table->string('status');
            $table->string('sorting_status')->default('staged');
            $table->boolean('received_by_sorting_center')->default(false);
            $table->timestamp('failed_returned_to_sorting_center_at')->nullable();
            $table->timestamp('failed_return_received_at')->nullable();
            $table->timestamps();
        });
    }

    private function fixture(string $rescheduleStatus = 'approved', bool $received = true): array
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'rider']);
        $rider = User::create([
            'name' => 'Rider One',
            'email' => 'rider-one@example.test',
            'password' => 'password',
            'status' => 'active',
            'logistic_id' => 10,
            'hub_id' => 1,
            'assigned_zone' => 'Santa Cruz',
            'logistic_status' => 'approved',
            'availability_status' => 'available',
            'current_load' => 0,
            'max_capacity' => 5,
        ]);
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $rider->id]);
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => 99,
            'order_number' => 'TEST-RESCHEDULE-1',
            'status' => 'placed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sellerOrderId = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId,
            'seller_id' => 77,
            'reschedule_status' => $rescheduleStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shipmentId = DB::table('shipments')->insertGetId([
            'logistic_id' => 10,
            'hub_id' => 1,
            'delivery_zone' => 'Santa Cruz',
            'seller_order_id' => $sellerOrderId,
            'tracking_number' => 'TRACK-RESCHEDULE-1',
            'status' => 'failed_return_at_sorting_center',
            'sorting_status' => 'staged',
            'received_by_sorting_center' => $received,
            'failed_returned_to_sorting_center_at' => now(),
            'failed_return_received_at' => $received ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return [Shipment::findOrFail($shipmentId), $rider];
    }

    public function test_reassignment_requires_logistics_receipt(): void
    {
        [$shipment, $rider] = $this->fixture('approved', false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Logistics must confirm receipt');
        RiderAssignmentService::assignRiderToShipment($shipment, $rider);
    }

    public function test_reassignment_requires_own_seller_approval(): void
    {
        [$shipment, $rider] = $this->fixture('requested', true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('approved seller-specific reschedule');
        RiderAssignmentService::assignRiderToShipment($shipment, $rider);
    }

    public function test_approved_return_can_be_reassigned(): void
    {
        [$shipment, $rider] = $this->fixture();
        RiderAssignmentService::assignRiderToShipment($shipment, $rider);
        $this->assertSame($rider->id, (int) $shipment->fresh()->rider_id);
        $this->assertSame('staged', $shipment->fresh()->status);
        $this->assertSame(1, (int) $rider->fresh()->current_load);
    }

    public function test_rider_from_other_logistics_cannot_be_assigned(): void
    {
        [$shipment, $rider] = $this->fixture();
        $rider->update(['logistic_id' => 20]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rider does not belong');
        RiderAssignmentService::assignRiderToShipment($shipment, $rider);
    }

    public function test_duplicate_assignment_is_blocked(): void
    {
        [$shipment, $rider] = $this->fixture();
        RiderAssignmentService::assignRiderToShipment($shipment, $rider);
        try {
            RiderAssignmentService::assignRiderToShipment($shipment, $rider);
            $this->fail('Duplicate assignment should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already has an assigned rider', $exception->getMessage());
        }
        $this->assertSame(1, (int) $rider->fresh()->current_load);
    }
}
