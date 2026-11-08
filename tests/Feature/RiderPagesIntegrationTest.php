<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\SQLiteTestCase;

class RiderPagesIntegrationTest extends SQLiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || DB::connection()->getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Rider page tests require SQLite in-memory.');
        }

        $this->withoutVite();
        $this->createSchema();
    }

    private function createSchema(): void
    {

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('type')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });


        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('logistic_status')->nullable();
            $table->foreignId('logistic_id')->nullable();
            $table->unsignedInteger('daily_deliveries_completed')->default(0);
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
            $table->string('delivery_status')->default('pending');
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('unpaid');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('amount_collected_minor')->default(0);
            $table->text('shipping_address')->nullable();
            $table->string('delivery_zone')->nullable();
            $table->decimal('customer_latitude', 10, 7)->nullable();
            $table->decimal('customer_longitude', 10, 7)->nullable();
            $table->timestamps();
        });
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('seller_id');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamps();
        });
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id');
            $table->foreignId('rider_id')->nullable();
            $table->string('tracking_number')->unique();
            $table->string('status')->default('assigned');
            $table->string('sorting_status')->default('staged');
            $table->boolean('received_by_sorting_center')->default(true);
            $table->timestamp('staged_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_zone')->nullable();
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
        DB::table('roles')->insert(['id' => 1, 'name' => 'rider', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function rider(string $name): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)) . '@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'logistic_status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_user')->insert(['role_id' => 1, 'user_id' => $id]);
        return User::findOrFail($id);
    }

    private function parcel(int $riderId, string $status, ?int $orderId = null, ?string $deliveredAt = null): array
    {
        if ($orderId === null) {
            $orderId = DB::table('orders')->insertGetId([
                'user_id' => $riderId,
                'order_number' => 'ORD-' . uniqid(),
                'total_minor' => 150000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Seller Owner', 'email' => uniqid('seller-') . '@example.com',
            'password' => bcrypt('password'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sellerId = DB::table('sellers')->insertGetId([
            'user_id' => $ownerId, 'name' => 'Test Seller', 'slug' => uniqid('shop-'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sellerOrderId = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId, 'seller_id' => $sellerId, 'total_minor' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tracking = 'TRACK-' . uniqid();
        $shipmentId = DB::table('shipments')->insertGetId([
            'seller_order_id' => $sellerOrderId,
            'rider_id' => $riderId,
            'tracking_number' => $tracking,
            'status' => $status,
            'delivered_at' => $deliveredAt,
            'staged_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$orderId, $shipmentId, $tracking];
    }

    private function payment(int $orderId, int $shipmentId, int $riderId, int $minor, string $date, string $status = 'paid'): void
    {
        DB::table('payments')->insert([
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'collected_by' => $riderId,
            'method' => 'cod',
            'status' => $status,
            'amount_minor' => $minor,
            'paid_at' => $date,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_deliveries_only_show_current_rider_shipments(): void
    {
        $mine = $this->rider('Rider One');
        $other = $this->rider('Rider Two');
        [, , $myTracking] = $this->parcel($mine->id, 'out_for_delivery');
        [, , $otherTracking] = $this->parcel($other->id, 'out_for_delivery');

        $this->actingAs($mine)->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee($myTracking)
            ->assertDontSee($otherTracking);
    }

    public function test_deliveries_show_two_parcels_from_one_parent_order(): void
    {
        $rider = $this->rider('Rider Multi');
        [$orderId, , $first] = $this->parcel($rider->id, 'assigned');
        [, , $second] = $this->parcel($rider->id, 'out_for_delivery', $orderId);

        $this->actingAs($rider)->get(route('rider.deliveries'))
            ->assertOk()->assertSee($first)->assertSee($second);
    }

    public function test_history_filters_delivered_and_failed_shipments(): void
    {
        $rider = $this->rider('Rider History');
        [, , $delivered] = $this->parcel($rider->id, 'delivered', null, '2026-10-09 10:00:00');
        [, , $failed] = $this->parcel($rider->id, 'delivery_failed');
        [, , $active] = $this->parcel($rider->id, 'assigned');

        $this->actingAs($rider)->get(route('rider.history', ['status' => 'delivered']))
            ->assertOk()->assertSee($delivered)->assertDontSee($failed)->assertDontSee($active);
        $this->get(route('rider.history', ['status' => 'delivery_failed']))
            ->assertOk()->assertSee($failed)->assertDontSee($delivered)->assertDontSee($active);
    }

    public function test_cod_summary_uses_shipment_payments_and_excludes_other_rider(): void
    {
        $mine = $this->rider('Rider Cash');
        $other = $this->rider('Rider Outside');
        [$orderId, $first] = $this->parcel($mine->id, 'delivered', null, '2026-10-10 12:00:00');
        [, $second] = $this->parcel($mine->id, 'delivered', $orderId, '2026-10-10 13:00:00');
        [$otherOrder, $foreign] = $this->parcel($other->id, 'delivered', null, '2026-10-10 14:00:00');
        $this->payment($orderId, $first, $mine->id, 50000, '2026-10-10 12:00:00');
        $this->payment($orderId, $second, $mine->id, 80025, '2026-10-10 13:00:00');
        $this->payment($otherOrder, $foreign, $other->id, 999999, '2026-10-10 14:00:00');

        $this->actingAs($mine)->get(route('rider.profit'))
            ->assertOk()
            ->assertViewHas('totalDelivered', 2)
            ->assertViewHas('collectionCount', 2)
            ->assertViewHas('totalCollected', 1300.25);
    }

    public function test_cod_summary_filters_by_payment_date(): void
    {
        $rider = $this->rider('Rider Dates');
        [$oldOrder, $oldShipment] = $this->parcel($rider->id, 'delivered', null, '2026-10-01 12:00:00');
        [$newOrder, $newShipment] = $this->parcel($rider->id, 'delivered', null, '2026-10-10 12:00:00');
        $this->payment($oldOrder, $oldShipment, $rider->id, 40000, '2026-10-01 12:00:00');
        $this->payment($newOrder, $newShipment, $rider->id, 75025, '2026-10-10 12:00:00');

        $this->actingAs($rider)->get(route('rider.profit', [
            'from_date' => '2026-10-10', 'to_date' => '2026-10-10',
        ]))->assertOk()
            ->assertViewHas('totalDelivered', 1)
            ->assertViewHas('collectionCount', 1)
            ->assertViewHas('totalCollected', 750.25);
    }

    public function test_other_rider_cannot_open_shipment_details(): void
    {
        $mine = $this->rider('Rider Guard');
        $other = $this->rider('Rider Owner');
        [, $shipmentId] = $this->parcel($other->id, 'assigned');

        $this->actingAs($mine)->get(route('rider.shipments.show', $shipmentId))
            ->assertForbidden();
    }
}
