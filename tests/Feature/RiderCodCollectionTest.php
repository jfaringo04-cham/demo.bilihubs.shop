<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\SQLiteTestCase;

class RiderCodCollectionTest extends SQLiteTestCase
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
            throw new \RuntimeException(
                'COD tests require SQLite in-memory database.'
            );
        }

        $this->createSchema();
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
            $table->string('order_number');
            $table->string('status')->default('pending');
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('unpaid');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('amount_collected_minor')
                ->nullable();
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
            $table->string('status');
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


        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_order_id')->nullable();

            $table->string('voucher_code_snapshot', 80);
            $table->string('funded_by', 20);

            $table->unsignedBigInteger('discount_minor');

            $table->string('status', 20)->default('reserved');

            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();

            $table->timestamps();

            $table->index(['voucher_id', 'user_id', 'status']);
            $table->index(['order_id', 'status']);
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_user')->insert([
            'role_id' => 1,
            'user_id' => $id,
        ]);

        return User::findOrFail($id);
    }

    private function order(
        int $totalMinor,
        string $paymentMethod = 'cod'
    ): int {
        return DB::table('orders')->insertGetId([
            'user_id' => 1,
            'order_number' => 'COD-' . uniqid(),
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'payment_status' => 'unpaid',
            'total_minor' => $totalMinor,
            'amount_collected_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function shipment(
        int $orderId,
        int $riderId,
        int $amountMinor,
        string $status = 'out_for_delivery'
    ): int {
        $sellerOrderId = DB::table('seller_orders')
            ->insertGetId([
                'order_id' => $orderId,
                'seller_id' => 1,
                'status' => 'pending',
                'total_minor' => $amountMinor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('shipments')->insertGetId([
            'seller_order_id' => $sellerOrderId,
            'rider_id' => $riderId,
            'tracking_number' => 'SHIP-' . uniqid(),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function collect(int $shipmentId, string $amount)
    {
        return $this->post(
            route('rider.shipments.collect', $shipmentId),
            ['amount_collected' => $amount]
        );
    }

    public function test_assigned_rider_can_collect_exact_cod(): void
    {
        $rider = $this->rider('rider1@example.com');
        $orderId = $this->order(150050);
        $shipmentId = $this->shipment(
            $orderId,
            $rider->id,
            150050
        );

        $this->actingAs($rider);

        $this->collect($shipmentId, '1500.50')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'collected_by' => $rider->id,
            'amount_minor' => 150050,
            'method' => 'cod',
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_collected_minor' => 150050,
            'payment_status' => 'paid',
        ]);
    }

    public function test_other_rider_cannot_collect_shipment(): void
    {
        $assigned = $this->rider('assigned@example.com');
        $other = $this->rider('other@example.com');

        $orderId = $this->order(50000);
        $shipmentId = $this->shipment(
            $orderId,
            $assigned->id,
            50000
        );

        $this->actingAs($other);

        $this->collect($shipmentId, '500.00')
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'unpaid',
            'amount_collected_minor' => 0,
        ]);
    }

    public function test_duplicate_collection_is_blocked(): void
    {
        $rider = $this->rider('duplicate@example.com');

        // A partially paid parent order allows us to exercise
        // shipment-specific duplicate protection.
        $orderId = $this->order(100000);
        $shipmentId = $this->shipment(
            $orderId,
            $rider->id,
            50000
        );

        $this->actingAs($rider);

        $this->collect($shipmentId, '500.00')
            ->assertSessionHas('success');

        $this->collect($shipmentId, '500.00')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_collected_minor' => 50000,
            'payment_status' => 'partially_paid',
        ]);
    }

    public function test_incorrect_amount_is_rejected(): void
    {
        $rider = $this->rider('amount@example.com');

        $orderId = $this->order(75025);
        $shipmentId = $this->shipment(
            $orderId,
            $rider->id,
            75025
        );

        $this->actingAs($rider);

        $this->collect($shipmentId, '750.00')
            ->assertSessionHas('error');

        $this->collect($shipmentId, '751.00')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_collected_minor' => 0,
        ]);
    }

    public function test_pending_shipment_cannot_collect_cod(): void
    {
        $rider = $this->rider('pending@example.com');

        $orderId = $this->order(25000);
        $shipmentId = $this->shipment(
            $orderId,
            $rider->id,
            25000,
            'pending'
        );

        $this->actingAs($rider);

        $this->collect($shipmentId, '250.00')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_non_cod_order_cannot_collect(): void
    {
        $rider = $this->rider('noncod@example.com');

        $orderId = $this->order(40000, 'gcash');
        $shipmentId = $this->shipment(
            $orderId,
            $rider->id,
            40000
        );

        $this->actingAs($rider);

        $this->collect($shipmentId, '400.00')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_multi_seller_order_is_paid_only_after_all_shipments(): void
    {
        $rider1 = $this->rider('multi1@example.com');
        $rider2 = $this->rider('multi2@example.com');

        $orderId = $this->order(150000);

        $shipment1 = $this->shipment(
            $orderId,
            $rider1->id,
            60000
        );

        $shipment2 = $this->shipment(
            $orderId,
            $rider2->id,
            90000
        );

        $this->actingAs($rider1);

        $this->collect($shipment1, '600.00')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_collected_minor' => 60000,
            'payment_status' => 'partially_paid',
        ]);

        $this->actingAs($rider2);

        $this->collect($shipment2, '900.00')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_collected_minor' => 150000,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseCount('payments', 2);

        $this->assertDatabaseHas('payments', [
            'shipment_id' => $shipment1,
            'collected_by' => $rider1->id,
            'amount_minor' => 60000,
        ]);

        $this->assertDatabaseHas('payments', [
            'shipment_id' => $shipment2,
            'collected_by' => $rider2->id,
            'amount_minor' => 90000,
        ]);
    }

    public function test_collection_cannot_exceed_parent_order_total(): void
    {
        $rider = $this->rider('overpayment@example.com');

        $orderId = $this->order(50000);
        $shipmentId = $this->shipment(
            $orderId,
            $rider->id,
            60000
        );

        $this->actingAs($rider);

        $this->collect($shipmentId, '600.00')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount_collected_minor' => 0,
        ]);
    }
}
