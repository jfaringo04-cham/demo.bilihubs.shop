<?php

namespace Tests\Feature;

use App\Models\RiderWalletTransaction;
use App\Models\Shipment;
use App\Services\RiderWalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiderEarningsSplitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('rider_wallet_transactions');
        Schema::dropIfExists('rider_wallets');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('seller_orders');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
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
            $table->bigInteger('shipping_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_order_id');
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

        config()->set('rider_wallet.pickup_earning_minor', 1500);
        config()->set('rider_wallet.delivery_earning_minor', 2500);
        config()->set('rider_wallet.logistics_earning_minor', 1000);
    }

    private function makeShipment(int $shippingMinor = 5000, bool $sameRider = false): Shipment
    {
        $pickupRiderId = DB::table('users')->insertGetId([
            'name' => 'Pickup Rider',
            'email' => 'pickup-' . uniqid() . '@example.com',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deliveryRiderId = $sameRider
            ? $pickupRiderId
            : DB::table('users')->insertGetId([
                'name' => 'Delivery Rider',
                'email' => 'delivery-' . uniqid() . '@example.com',
                'password' => 'x',
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
            'shipping_minor' => $shippingMinor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shipmentId = DB::table('shipments')->insertGetId([
            'seller_order_id' => $sellerOrderId,
            'pickup_rider_id' => $pickupRiderId,
            'rider_id' => $deliveryRiderId,
            'tracking_number' => 'SPLIT-' . uniqid(),
            'status' => 'delivered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Shipment::findOrFail($shipmentId);
    }

    public function test_confirmed_shipment_splits_rider_share_between_pickup_and_final_rider(): void
    {
        $shipment = $this->makeShipment();

        app(RiderWalletService::class)->creditDeliveredShipment($shipment);

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
        $this->assertSame(
            4000,
            (int) DB::table('rider_wallet_transactions')
                ->where('shipment_id', $shipment->id)
                ->sum('amount_minor')
        );
    }

    public function test_same_rider_can_receive_both_legs_without_duplicate_wallet(): void
    {
        $shipment = $this->makeShipment(5000, true);

        app(RiderWalletService::class)->creditDeliveredShipment($shipment);

        $this->assertSame(1, DB::table('rider_wallets')->count());
        $this->assertSame(4000, (int) DB::table('rider_wallets')->value('available_balance_minor'));
        $this->assertSame(2, RiderWalletTransaction::where('shipment_id', $shipment->id)->count());
    }

    public function test_split_is_idempotent_when_credit_is_retried(): void
    {
        $shipment = $this->makeShipment();
        $service = app(RiderWalletService::class);

        $service->creditDeliveredShipment($shipment);
        $service->creditDeliveredShipment($shipment->fresh());

        $this->assertSame(2, RiderWalletTransaction::where('shipment_id', $shipment->id)->count());
        $this->assertSame(
            4000,
            (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
        );
    }

    public function test_combined_credit_never_exceeds_shipping_fee(): void
    {
        $shipment = $this->makeShipment(3000);

        app(RiderWalletService::class)->creditDeliveredShipment($shipment);

        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'pickup_credit',
            'amount_minor' => 1500,
        ]);
        $this->assertDatabaseHas('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'delivery_credit',
            'amount_minor' => 500,
        ]);
        $this->assertSame(
            2000,
            (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
        );
    }

    public function test_legacy_full_delivery_credit_does_not_receive_extra_pickup_credit(): void
    {
        $shipment = $this->makeShipment();

        $finalWalletId = DB::table('rider_wallets')->insertGetId([
            'rider_id' => $shipment->rider_id,
            'available_balance_minor' => 5000,
            'pending_balance_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('rider_wallet_transactions')->insert([
            'rider_wallet_id' => $finalWalletId,
            'shipment_id' => $shipment->id,
            'type' => 'delivery_credit',
            'amount_minor' => 5000,
            'balance_after_minor' => 5000,
            'reference' => 'DELIVERY-' . $shipment->id,
            'description' => 'Legacy full rider credit.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RiderWalletService::class)->creditDeliveredShipment($shipment);

        $this->assertDatabaseMissing('rider_wallet_transactions', [
            'shipment_id' => $shipment->id,
            'type' => 'pickup_credit',
        ]);
        $this->assertSame(
            5000,
            (int) RiderWalletTransaction::where('shipment_id', $shipment->id)->sum('amount_minor')
        );
    }
}
