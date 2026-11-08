<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\SQLiteTestCase;

class RiderDeliverySuccessTest extends SQLiteTestCase
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
        Storage::fake('supabase_private');
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

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('seller_order_id');
            $table->unsignedBigInteger('subtotal_minor');
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('seller_id');
            $table->unsignedBigInteger('order_total_minor')->nullable();
            $table->decimal('rate', 5, 2)->default(10);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'seller_id']);
        });

        Schema::create('platform_revenue_entries', function (Blueprint $table) {
            $table->id();

            $table->string('revenue_type', 40);
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('entry_type', 20)->default('earned');

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('seller_order_id')
                ->nullable()
                ->constrained('seller_orders')
                ->restrictOnDelete();

            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('PHP');
            $table->timestampTz('recognized_at');

            $table->foreignId('reversal_of_entry_id')
                ->nullable()
                ->unique()
                ->constrained('platform_revenue_entries')
                ->restrictOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['revenue_type', 'source_type', 'source_id', 'entry_type'],
                'platform_revenue_source_unique'
            );

            $table->index('order_id');
            $table->index('seller_order_id');
            $table->index('recognized_at');
            $table->index('revenue_type');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->nullable();
            $table->text('link')->nullable();
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

        $sellerOwnerId = DB::table('users')->insertGetId([
            'name' => 'Shop Owner',
            'email' => 'seller-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sellerId = DB::table('sellers')->insertGetId([
            'user_id' => $sellerOwnerId,
            'name' => 'Test Shop',
            'slug' => 'test-shop-' . uniqid(),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sellerOrderId = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId,
            'seller_id' => $sellerId,
            'status' => 'pending',
            'total_minor' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'seller_order_id' => $sellerOrderId,
            'subtotal_minor' => 45000,
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


    private function collectCod(int $shipmentId, string $amount = '500.00'): void
    {
        $this->post(route('rider.shipments.collect', $shipmentId), [
            'amount_collected' => $amount,
        ])->assertSessionHas('success');
    }

    public function test_paid_cod_delivery_updates_shipment_order_rider_proof_and_commission(): void
    {
        $rider = $this->rider('success-delivery@example.com');
        [$orderId, $sellerOrderId, $shipmentId] = $this->parcel($rider->id);
        $this->actingAs($rider);
        $this->collectCod($shipmentId);

        $this->deliver($shipmentId, [
            'delivered_to' => 'Customer',
            'delivery_signature' => 'Customer signature',
        ])->assertSessionHas('success');

        $shipment = DB::table('shipments')->find($shipmentId);
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertNotEmpty($shipment->proof_of_delivery);
        Storage::disk('supabase_private')->assertExists($shipment->proof_of_delivery);
        $this->assertSame('Customer', $shipment->delivered_to);
        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrderId, 'status' => 'delivered']);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId, 'status' => 'delivered',
            'delivery_status' => 'delivered', 'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $rider->id, 'current_load' => 0,
            'daily_deliveries_completed' => 1, 'availability_status' => 'available',
        ]);

        $sellerUserId = DB::table('sellers')
            ->where('id', DB::table('seller_orders')->where('id', $sellerOrderId)->value('seller_id'))
            ->value('user_id');
        $this->assertDatabaseHas('commissions', [
            'order_id' => $orderId, 'seller_id' => $sellerUserId,
            'order_total_minor' => 45000, 'amount_minor' => 4500,
            'status' => 'pending',
        ]);
        $this->assertSame(1, DB::table('commissions')->where('order_id', $orderId)->count());
    }

    public function test_delivered_shipment_cannot_generate_second_commission_or_second_rider_increment(): void
    {
        $rider = $this->rider('idempotent-delivery@example.com');
        [$orderId, , $shipmentId] = $this->parcel($rider->id);
        $this->actingAs($rider);
        $this->collectCod($shipmentId);
        $this->deliver($shipmentId)->assertSessionHas('success');
        $this->deliver($shipmentId)->assertSessionHasErrors('delivery_status');

        $this->assertSame(1, DB::table('commissions')->where('order_id', $orderId)->count());
        $this->assertDatabaseHas('users', [
            'id' => $rider->id, 'current_load' => 0,
            'daily_deliveries_completed' => 1,
        ]);
    }

    public function test_first_of_two_seller_parcels_only_partially_delivers_parent_order(): void
    {
        $rider = $this->rider('partial-delivery@example.com');
        [$orderId, $firstSellerOrderId, $firstShipmentId] = $this->parcel($rider->id);

        $secondSellerOwner = DB::table('users')->insertGetId([
            'name' => 'Second Shop Owner',
            'email' => 'second-seller@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $secondSeller = DB::table('sellers')->insertGetId([
            'user_id' => $secondSellerOwner,
            'name' => 'Second Shop',
            'slug' => 'second-shop',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $secondSellerOrder = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId, 'seller_id' => $secondSeller,
            'status' => 'pending', 'total_minor' => 30000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $orderId, 'seller_order_id' => $secondSellerOrder,
            'subtotal_minor' => 28000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $secondShipment = DB::table('shipments')->insertGetId([
            'seller_order_id' => $secondSellerOrder, 'rider_id' => $rider->id,
            'tracking_number' => 'SECOND-' . uniqid(),
            'status' => 'out_for_delivery', 'sorting_status' => 'staged',
            'received_by_sorting_center' => true, 'staged_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('orders')->where('id', $orderId)->update([
            'total_minor' => 80000, 'amount_collected_minor' => 0,
        ]);
        DB::table('users')->where('id', $rider->id)->update(['current_load' => 2]);

        $this->actingAs($rider);
        $this->collectCod($firstShipmentId);
        $this->deliver($firstShipmentId)->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId, 'delivery_status' => 'partially_delivered',
            'payment_status' => 'partially_paid',
        ]);
        $this->assertDatabaseHas('seller_orders', [
            'id' => $firstSellerOrderId, 'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('seller_orders', [
            'id' => $secondSellerOrder, 'status' => 'pending',
        ]);
        $this->assertSame(1, DB::table('commissions')->where('order_id', $orderId)->count());

        $this->collectCod($secondShipment, '300.00');
        $this->deliver($secondShipment)->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId, 'status' => 'delivered',
            'delivery_status' => 'delivered', 'payment_status' => 'paid',
            'amount_collected_minor' => 80000,
        ]);
        $this->assertSame(2, DB::table('commissions')->where('order_id', $orderId)->count());
        $this->assertDatabaseHas('users', [
            'id' => $rider->id, 'current_load' => 0,
            'daily_deliveries_completed' => 2,
        ]);
    }
}
