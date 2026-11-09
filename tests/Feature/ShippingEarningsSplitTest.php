<?php

namespace Tests\Feature;

use App\Models\LogisticWalletTransaction;
use App\Models\RiderWalletTransaction;
use App\Models\Shipment;
use App\Services\LogisticWalletService;
use App\Services\RiderWalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShippingEarningsSplitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Isolate this legacy test schema from the PostgreSQL test database.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $schema = Schema::connection('sqlite');

        foreach ([
            'logistic_wallet_transactions',
            'logistic_wallets',
            'rider_wallet_transactions',
            'rider_wallets',
            'shipments',
            'seller_orders',
            'orders',
            'logistics',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('logistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('company_name')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->timestamp('confirmed_received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('logistic_id')->nullable();
            $table->bigInteger('shipping_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedBigInteger('logistic_id')->nullable();
            $table->unsignedBigInteger('pickup_rider_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('qr_token')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('rider_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id')->unique();
            $table->bigInteger('available_balance_minor')->default(0);
            $table->bigInteger('pending_balance_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('rider_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_wallet_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('type', 50);
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('reference', 150)->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'type']);
        });

        Schema::create('logistic_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_id')->unique();
            $table->bigInteger('available_balance_minor')->default(0);
            $table->bigInteger('pending_balance_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('logistic_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_wallet_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('type', 50);
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('reference', 150)->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'type']);
        });

        // Enable new policy for dynamically-created test orders.
        config()->set('shipping.bonus_effective_at', '2020-01-01T00:00:00+08:00');
        config()->set('rider_wallet.pickup_earning_minor', 1500);
        config()->set('rider_wallet.delivery_earning_minor', 2500);
        config()->set('rider_wallet.logistics_earning_minor', 1000);
    }

    private function makeShipment(int $shippingMinor = 5000): Shipment
    {
        $pickupRiderId = DB::table('users')->insertGetId([
            'name' => 'Pickup Rider',
            'email' => 'pickup-' . uniqid() . '@example.com',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deliveryRiderId = DB::table('users')->insertGetId([
            'name' => 'Final Rider',
            'email' => 'final-' . uniqid() . '@example.com',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $logisticId = DB::table('logistics')->insertGetId([
            'company_name' => 'Test Logistics',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'confirmed_received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sellerOrderId = DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId,
            'logistic_id' => $logisticId,
            'shipping_minor' => $shippingMinor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shipmentId = DB::table('shipments')->insertGetId([
            'seller_order_id' => $sellerOrderId,
            'logistic_id' => $logisticId,
            'pickup_rider_id' => $pickupRiderId,
            'rider_id' => $deliveryRiderId,
            'tracking_number' => 'MONEY-' . uniqid(),
            'status' => 'delivered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Shipment::findOrFail($shipmentId);
    }

    private function creditAll(Shipment $shipment): void
    {
        app(RiderWalletService::class)->creditDeliveredShipment($shipment);
        app(LogisticWalletService::class)->creditConfirmedShipment($shipment->fresh());
    }

    public function test_standard_fifty_peso_shipping_is_split_fifteen_twenty_five_ten(): void
    {
        $shipment = $this->makeShipment();
        $this->creditAll($shipment);

        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'pickup_credit',
            'amount_minor' => 1500,
        ]);
        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'delivery_credit',
            'amount_minor' => 2500,
        ]);
        $this->assertDatabaseHas('logistic_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'shipment_credit',
            'amount_minor' => 1000,
        ]);

        $total = (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
            + (int) LogisticWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor');

        $this->assertSame(5000, $total);
    }

    public function test_extra_four_pesos_is_shared_between_delivery_rider_and_logistics(): void
    {
        config()->set('shipping.base_fee_minor', 5000);
        $shipment = $this->makeShipment(5400);
        $this->creditAll($shipment);

        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'pickup_credit', 'amount_minor' => 1500,
        ]);
        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'delivery_credit', 'amount_minor' => 2700,
        ]);
        $this->assertDatabaseHas('logistic_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'shipment_credit', 'amount_minor' => 1200,
        ]);
        $total = (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
            + (int) LogisticWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor');
        $this->assertSame(5400, $total);
    }

    public function test_extra_eight_pesos_is_shared_and_credits_are_idempotent(): void
    {
        config()->set('shipping.base_fee_minor', 5000);
        $shipment = $this->makeShipment(5800);
        $this->creditAll($shipment);
        $this->creditAll($shipment->fresh());

        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'delivery_credit', 'amount_minor' => 2900,
        ]);
        $this->assertDatabaseHas('logistic_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'shipment_credit', 'amount_minor' => 1400,
        ]);
        $this->assertSame(2, RiderWalletTransaction::where('shipment_id', $shipment->id)->count());
        $this->assertSame(1, LogisticWalletTransaction::where('shipment_id', $shipment->id)->count());
    }

    public function test_linehaul_preserves_base_shares_and_splits_extra_four_pesos(): void
    {
        config()->set('shipping.base_fee_minor', 5000);
        config()->set('rider_wallet.logistics_earning_minor', 500);

        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('linehaul_rider_id')->nullable();
        });

        $shipment = $this->makeShipment(5400);
        $linehaulRiderId = DB::table('users')->insertGetId([
            'name' => 'Linehaul Rider',
            'email' => 'linehaul-' . uniqid() . '@example.com',
            'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shipments')->where('id', $shipment->id)->update([
            'linehaul_rider_id' => $linehaulRiderId,
        ]);
        $shipment = $shipment->fresh();

        $this->creditAll($shipment);

        foreach ([
            'pickup_credit' => 1500,
            'delivery_credit' => 2700,
            'linehaul_credit' => 500,
        ] as $type => $amount) {
            $this->assertDatabaseHas('rider_wallet_transactions', [
                'shipment_id' => $shipment->id,
                'type' => $type,
                'amount_minor' => $amount,
            ]);
        }
        $this->assertDatabaseHas('logistic_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'shipment_credit', 'amount_minor' => 700,
        ]);
        $total = (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
            + (int) LogisticWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor');
        $this->assertSame(5400, $total);
    }

    public function test_pre_policy_eighty_peso_order_keeps_legacy_payout_when_confirmed_later(): void
    {
        config()->set('shipping.bonus_effective_at', '2026-11-10T00:00:00+08:00');
        $shipment = $this->makeShipment(8000);

        // Simulate a real in-flight order placed before the new fee policy.
        DB::table('seller_orders')->where('id', $shipment->seller_order_id)->update([
            'created_at' => '2026-11-08 08:00:00',
        ]);

        $this->creditAll($shipment);

        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'pickup_credit', 'amount_minor' => 1500,
        ]);
        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'delivery_credit', 'amount_minor' => 2500,
        ]);
        $this->assertDatabaseHas('logistic_wallet_transactions', [
            'shipment_id' => $shipment->id, 'type' => 'shipment_credit', 'amount_minor' => 4000,
        ]);
        $this->assertSame(8000,
            (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
            + (int) LogisticWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
        );
    }

    public function test_repeated_credit_does_not_duplicate_any_share(): void
    {
        $shipment = $this->makeShipment();
        $this->creditAll($shipment);
        $this->creditAll($shipment->fresh());

        $this->assertSame(2, RiderWalletTransaction::where('shipment_id', $shipment->id)->count());
        $this->assertSame(1, LogisticWalletTransaction::where('shipment_id', $shipment->id)->count());
    }

    public function test_low_shipping_fee_is_capped_without_exceeding_shipping_fee(): void
    {
        $shipment = $this->makeShipment(3000);
        $this->creditAll($shipment);

        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'pickup_credit',
            'amount_minor' => 1500,
        ]);

        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'delivery_credit',
            'amount_minor' => 1500,
        ]);

        $this->assertDatabaseMissing('logistic_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'shipment_credit',
        ]);

        $total = (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
            + (int) LogisticWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor');

        $this->assertSame(3000, $total);
    }

    public function test_legacy_full_fifty_peso_rider_credit_blocks_extra_logistics_credit(): void
    {
        $shipment = $this->makeShipment();

        $walletId = DB::table('rider_wallets')->insertGetId([
            'rider_id' => $shipment->rider_id,
            'available_balance_minor' => 5000,
            'pending_balance_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('rider_wallet_transactions')->insert([
            'rider_wallet_id' => $walletId,
            'shipment_id' => $shipment->id,
            'type' => 'delivery_credit',
            'amount_minor' => 5000,
            'balance_after_minor' => 5000,
            'reference' => 'DELIVERY-' . $shipment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->creditAll($shipment);

        $this->assertDatabaseMissing('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'pickup_credit',
        ]);
        $this->assertDatabaseMissing('logistic_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'shipment_credit',
        ]);
    }
}
