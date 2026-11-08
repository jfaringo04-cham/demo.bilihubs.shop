<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\SQLiteTestCase;

class RiderDeliveryFinalizationTest extends SQLiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            DB::connection()->getDriverName() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new \RuntimeException('Delivery tests require SQLite in-memory database.');
        }

        $this->createSchema();
        Storage::fake('public');
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('logistic_status')->nullable();
            $table->unsignedInteger('current_load')->default(1);
            $table->unsignedInteger('max_capacity')->default(5);
            $table->unsignedInteger('daily_deliveries_completed')->default(0);
            $table->string('availability_status')->default('busy');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('rider_id')->nullable();
            $table->string('order_number');
            $table->string('status')->default('pending');
            $table->string('delivery_status')->default('assigned_to_rider');
            $table->timestamp('delivered_at')->nullable();
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('unpaid');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('amount_collected_minor')->default(0);
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('seller_id');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('total_minor');
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id');
            $table->foreignId('rider_id')->nullable();
            $table->string('tracking_number')->unique();
            $table->string('status')->default('picked_up');
            $table->string('sorting_status')->default('staged');
            $table->boolean('received_by_sorting_center')->default(true);
            $table->timestamp('staged_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('proof_of_delivery')->nullable();
            $table->string('delivery_signature')->nullable();
            $table->string('delivered_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('shipment_id')->nullable()->unique();
            $table->foreignId('collected_by')->nullable();
            $table->string('method');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('PHP');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'rider',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rider(string $email): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Test Rider',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
            'logistic_status' => 'approved',
            'current_load' => 1,
            'max_capacity' => 5,
            'daily_deliveries_completed' => 0,
            'availability_status' => 'busy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_user')->insert(['role_id' => 1, 'user_id' => $id]);

        return User::findOrFail($id);
    }

    private function parcel(int $riderId, string $status = 'out_for_delivery', string $method = 'cod'): array
    {
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $riderId,
            'rider_id' => $riderId,
            'order_number' => 'DEL-' . uniqid(),
            'status' => 'pending',
            'delivery_status' => 'out_for_delivery',
            'payment_method' => $method,
            'payment_status' => 'unpaid',
            'total_minor' => 50000,
            'amount_collected_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sellerOrderId = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId,
            'seller_id' => $riderId,
            'status' => 'pending',
            'total_minor' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shipmentId = DB::table('shipments')->insertGetId([
            'seller_order_id' => $sellerOrderId,
            'rider_id' => $riderId,
            'tracking_number' => 'DEL-SHIP-' . uniqid(),
            'status' => $status,
            'sorting_status' => 'staged',
            'received_by_sorting_center' => true,
            'staged_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$orderId, $sellerOrderId, $shipmentId];
    }

    private function deliver(int $shipmentId, array $fields = [])
    {
        return $this->post(route('rider.shipments.updateStatus', $shipmentId), array_merge([
            'delivery_status' => 'delivered',
            'proof_of_delivery' => UploadedFile::fake()->image('delivery.jpg'),
        ], $fields));
    }

    public function test_other_rider_cannot_finalize_shipment(): void
    {
        $assigned = $this->rider('assigned-delivery@example.com');
        $other = $this->rider('other-delivery@example.com');
        [$orderId, , $shipmentId] = $this->parcel($assigned->id);

        $this->actingAs($other);
        $this->deliver($shipmentId)->assertForbidden();

        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'status' => 'out_for_delivery']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
    }

    public function test_shipment_requires_sorting_center_receipt(): void
    {
        $rider = $this->rider('receipt@example.com');
        [, , $shipmentId] = $this->parcel($rider->id, 'picked_up');
        DB::table('shipments')->where('id', $shipmentId)->update(['received_by_sorting_center' => false]);

        $this->actingAs($rider);
        $this->post(route('rider.shipments.updateStatus', $shipmentId), [
            'delivery_status' => 'out_for_delivery',
        ])->assertSessionHasErrors('delivery_status');

        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'status' => 'picked_up']);
    }

    public function test_shipment_requires_staged_sorting_status(): void
    {
        $rider = $this->rider('staging@example.com');
        [, , $shipmentId] = $this->parcel($rider->id, 'picked_up');
        DB::table('shipments')->where('id', $shipmentId)->update(['sorting_status' => 'pending']);

        $this->actingAs($rider);
        $this->post(route('rider.shipments.updateStatus', $shipmentId), [
            'delivery_status' => 'out_for_delivery',
        ])->assertSessionHasErrors('delivery_status');
    }

    public function test_shipment_requires_staged_timestamp(): void
    {
        $rider = $this->rider('timestamp@example.com');
        [, , $shipmentId] = $this->parcel($rider->id, 'picked_up');
        DB::table('shipments')->where('id', $shipmentId)->update(['staged_at' => null]);

        $this->actingAs($rider);
        $this->post(route('rider.shipments.updateStatus', $shipmentId), [
            'delivery_status' => 'out_for_delivery',
        ])->assertSessionHasErrors('delivery_status');
    }

    public function test_cannot_deliver_before_out_for_delivery(): void
    {
        $rider = $this->rider('sequence@example.com');
        [, , $shipmentId] = $this->parcel($rider->id, 'picked_up');

        $this->actingAs($rider);
        $this->deliver($shipmentId)->assertSessionHasErrors('delivery_status');
        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'status' => 'picked_up']);
    }

    public function test_proof_of_delivery_is_required(): void
    {
        $rider = $this->rider('proof@example.com');
        [, , $shipmentId] = $this->parcel($rider->id);

        $this->actingAs($rider);
        $this->post(route('rider.shipments.updateStatus', $shipmentId), [
            'delivery_status' => 'delivered',
        ])->assertSessionHasErrors('proof_of_delivery');
    }

    public function test_cod_must_be_collected_before_delivery(): void
    {
        $rider = $this->rider('cod-before-delivery@example.com');
        [$orderId, , $shipmentId] = $this->parcel($rider->id);

        $this->actingAs($rider);
        $this->deliver($shipmentId)->assertSessionHasErrors('delivery_status');

        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'status' => 'out_for_delivery']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_finalized_shipment_cannot_be_delivered_again(): void
    {
        $rider = $this->rider('duplicate-delivery@example.com');
        [, , $shipmentId] = $this->parcel($rider->id, 'delivered');

        $this->actingAs($rider);
        $this->deliver($shipmentId)->assertSessionHasErrors('delivery_status');
        $this->assertDatabaseHas('users', [
            'id' => $rider->id,
            'daily_deliveries_completed' => 0,
            'current_load' => 1,
        ]);
    }

    public function test_legacy_order_level_delivery_is_blocked(): void
    {
        $rider = $this->rider('legacy-delivery@example.com');
        [$orderId, , $shipmentId] = $this->parcel($rider->id);

        $this->actingAs($rider);
        $this->post(route('rider.orders.updateStatus', $orderId), [
            'delivery_status' => 'delivered',
        ])->assertSessionHasErrors('delivery_status');

        $this->assertDatabaseHas('shipments', ['id' => $shipmentId, 'status' => 'out_for_delivery']);
    }

    public function test_legacy_order_level_cod_collection_is_blocked(): void
    {
        $rider = $this->rider('legacy-collection@example.com');
        [$orderId] = $this->parcel($rider->id);

        $this->actingAs($rider);
        $this->post(route('rider.orders.collect', $orderId), [
            'amount_collected' => '500.00',
        ])->assertRedirect();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'amount_collected_minor' => 0,
        ]);
    }
}
