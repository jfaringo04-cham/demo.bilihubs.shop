<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\VoucherCalculationService;
use App\Services\VoucherClaimService;
use App\Services\VoucherRedemptionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\SQLiteTestCase;

class VoucherIntegrationTest extends SQLiteTestCase
{
    private int $buyerId = 1001;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('approved');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('discounted_price_minor')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->timestamp('discount_starts_at')->nullable();
            $table->timestamp('discount_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20);
            $table->foreignId('seller_id')->nullable();
            $table->string('name');
            $table->string('code', 80)->unique();
            $table->text('description')->nullable();
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('discount_value_minor')->nullable();
            $table->unsignedSmallInteger('discount_percent_bps')->nullable();
            $table->unsignedBigInteger('minimum_spend_minor')->default(0);
            $table->unsignedBigInteger('maximum_discount_minor')->nullable();
            $table->string('product_scope', 20)->default('all');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('voucher_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id');
            $table->foreignId('product_id');
            $table->unique(['voucher_id', 'product_id']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id');
            $table->unsignedBigInteger('user_id');
            $table->foreignId('order_id');
            $table->foreignId('seller_order_id')->nullable();
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

        Schema::create('buyer_voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamps();
            $table->unique(['voucher_id', 'user_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('payment_status')->default('pending');
            $table->timestamps();
        });
    }

    private function createVoucher(array $overrides = []): Voucher
    {
        $now = now();

        $id = DB::table('vouchers')->insertGetId(array_merge([
            'owner_type' => 'platform',
            'seller_id' => null,
            'name' => 'Test Voucher',
            'code' => 'TEST-' . strtoupper(bin2hex(random_bytes(3))),
            'description' => null,
            'discount_type' => 'fixed',
            'discount_value_minor' => 100,
            'discount_percent_bps' => null,
            'minimum_spend_minor' => 0,
            'maximum_discount_minor' => null,
            'product_scope' => 'all',
            'usage_limit' => 10,
            'per_user_limit' => 1,
            'usage_count' => 0,
            'starts_at' => $now->copy()->subDay(),
            'ends_at' => $now->copy()->addDay(),
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));

        return Voucher::query()->findOrFail($id);
    }

    private function createProduct(int $priceMinor, ?int $sellerId = null): Product
    {
        $now = now();

        $id = DB::table('products')->insertGetId([
            'seller_id' => $sellerId,
            'name' => 'Test Product ' . $priceMinor,
            'price_minor' => $priceMinor,
            'discounted_price_minor' => null,
            'discount_percent' => null,
            'discount_starts_at' => null,
            'discount_ends_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Product::query()->findOrFail($id);
    }

    private function claim(Voucher $voucher, ?int $userId = null): void
    {
        app(VoucherClaimService::class)->claim(
            $userId ?? $this->buyerId,
            (int) $voucher->id
        );
    }

    public function test_voucher_cannot_be_calculated_before_buyer_claims_it(): void
    {
        $voucher = $this->createVoucher();
        $product = $this->createProduct(500);
        $cart = collect([(object) [
            'product' => $product,
            'variation' => null,
            'quantity' => 1,
        ]]);

        try {
            app(VoucherCalculationService::class)->calculate(
                $voucher->code,
                $cart,
                $this->buyerId
            );
            $this->fail('An unclaimed voucher must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('voucher_code', $exception->errors());
        }
    }

    public function test_duplicate_voucher_claim_is_rejected(): void
    {
        $voucher = $this->createVoucher();
        $this->claim($voucher);

        $this->expectException(ValidationException::class);

        $this->claim($voucher);
    }

    public function test_largest_remainder_allocation_never_exceeds_an_items_subtotal(): void
    {
        $voucher = $this->createVoucher([
            'discount_value_minor' => 100,
        ]);
        $this->claim($voucher);

        $products = [
            $this->createProduct(100),
            $this->createProduct(100),
            $this->createProduct(1),
        ];

        $cart = collect($products)->map(fn (Product $product) => (object) [
            'product' => $product,
            'variation' => null,
            'quantity' => 1,
        ]);

        $result = app(VoucherCalculationService::class)->calculate(
            $voucher->code,
            $cart,
            $this->buyerId
        );

        $allocations = array_column($result['items'], 'discount_minor');

        $this->assertSame(100, $result['discount_minor']);
        $this->assertSame(100, array_sum($allocations));
        $this->assertSame([50, 50, 0], $allocations);

        foreach ($result['items'] as $item) {
            $this->assertLessThanOrEqual(
                $item['eligible_subtotal_minor'],
                $item['discount_minor']
            );
        }
    }

    public function test_reserved_redemption_counts_against_the_buyers_usage_limit(): void
    {
        $voucher = $this->createVoucher(['per_user_limit' => 1]);
        $this->claim($voucher);

        DB::table('voucher_redemptions')->insert([
            'voucher_id' => $voucher->id,
            'user_id' => $this->buyerId,
            'order_id' => 5001,
            'seller_order_id' => null,
            'voucher_code_snapshot' => $voucher->code,
            'funded_by' => 'platform',
            'discount_minor' => 100,
            'status' => VoucherRedemption::STATUS_RESERVED,
            'redeemed_at' => null,
            'reversed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->createProduct(500);
        $cart = collect([(object) [
            'product' => $product,
            'variation' => null,
            'quantity' => 1,
        ]]);

        $this->expectException(ValidationException::class);

        app(VoucherCalculationService::class)->calculate(
            $voucher->code,
            $cart,
            $this->buyerId
        );
    }

    public function test_reversed_redemption_does_not_consume_the_buyers_usage_limit(): void
    {
        $voucher = $this->createVoucher(['per_user_limit' => 1]);
        $this->claim($voucher);

        DB::table('voucher_redemptions')->insert([
            'voucher_id' => $voucher->id,
            'user_id' => $this->buyerId,
            'order_id' => 5002,
            'seller_order_id' => null,
            'voucher_code_snapshot' => $voucher->code,
            'funded_by' => 'platform',
            'discount_minor' => 100,
            'status' => VoucherRedemption::STATUS_REVERSED,
            'redeemed_at' => null,
            'reversed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = $this->createProduct(500);
        $cart = collect([(object) [
            'product' => $product,
            'variation' => null,
            'quantity' => 1,
        ]]);

        $result = app(VoucherCalculationService::class)->calculate(
            $voucher->code,
            $cart,
            $this->buyerId
        );

        $this->assertSame(100, $result['discount_minor']);
    }

    public function test_reserved_voucher_is_redeemed_on_payment_and_reversed_only_once(): void
    {
        $voucher = $this->createVoucher(['usage_limit' => 2, 'per_user_limit' => 2]);
        $this->claim($voucher);
        $orderId = DB::table('orders')->insertGetId([
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(VoucherRedemptionService::class);
        $redemption = $service->recordForOrder(
            $orderId,
            null,
            (int) $voucher->id,
            $this->buyerId,
            [
                'code' => $voucher->code,
                'funded_by' => 'platform',
                'discount_minor' => 100,
            ],
            true
        );

        $this->assertSame(VoucherRedemption::STATUS_RESERVED, $redemption->status);
        $this->assertSame(1, (int) $voucher->fresh()->usage_count);

        $this->assertSame(1, $service->redeemForOrder($orderId));
        $this->assertDatabaseHas('voucher_redemptions', [
            'id' => $redemption->id,
            'status' => VoucherRedemption::STATUS_REDEEMED,
        ]);
        $this->assertSame(1, (int) $voucher->fresh()->usage_count);

        $this->assertSame(1, $service->reverseForOrder($orderId));
        $this->assertSame(0, (int) $voucher->fresh()->usage_count);

        $this->assertSame(0, $service->reverseForOrder($orderId));
        $this->assertSame(0, (int) $voucher->fresh()->usage_count);

        $this->assertDatabaseHas('voucher_redemptions', [
            'id' => $redemption->id,
            'status' => VoucherRedemption::STATUS_REVERSED,
        ]);
    }

    public function test_active_reservations_count_against_the_global_usage_limit(): void
    {
        $voucher = $this->createVoucher([
            'usage_limit' => 1,
            'per_user_limit' => 2,
        ]);
        $this->claim($voucher, $this->buyerId);

        $orderId = DB::table('orders')->insertGetId([
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(VoucherRedemptionService::class)->recordForOrder(
            $orderId,
            null,
            (int) $voucher->id,
            $this->buyerId,
            ['code' => $voucher->code, 'funded_by' => 'platform', 'discount_minor' => 100],
            true
        );

        $this->assertSame(1, (int) $voucher->fresh()->usage_count);

        $this->expectException(ValidationException::class);

        app(VoucherRedemptionService::class)->recordForOrder(
            $orderId + 1,
            null,
            (int) $voucher->id,
            $this->buyerId + 1,
            ['code' => $voucher->code, 'funded_by' => 'platform', 'discount_minor' => 100],
            true
        );
    }

    public function test_order_payment_status_observer_redeems_or_reverses_reservations(): void
    {
        $voucher = $this->createVoucher(['usage_limit' => 5, 'per_user_limit' => 5]);
        $this->claim($voucher);
        $service = app(VoucherRedemptionService::class);

        $paidOrderId = DB::table('orders')->insertGetId([
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $service->recordForOrder(
            $paidOrderId,
            null,
            (int) $voucher->id,
            $this->buyerId,
            ['code' => $voucher->code, 'funded_by' => 'platform', 'discount_minor' => 100],
            true
        );

        $paidOrder = Order::query()->findOrFail($paidOrderId);
        $paidOrder->update(['payment_status' => 'partially_paid']);

        $this->assertDatabaseHas('voucher_redemptions', [
            'order_id' => $paidOrderId,
            'status' => VoucherRedemption::STATUS_RESERVED,
        ]);

        $paidOrder->update(['payment_status' => 'paid']);

        $this->assertDatabaseHas('voucher_redemptions', [
            'order_id' => $paidOrderId,
            'status' => VoucherRedemption::STATUS_REDEEMED,
        ]);

        $failedOrderId = DB::table('orders')->insertGetId([
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $service->recordForOrder(
            $failedOrderId,
            null,
            (int) $voucher->id,
            $this->buyerId,
            ['code' => $voucher->code, 'funded_by' => 'platform', 'discount_minor' => 100],
            true
        );

        $failedOrder = Order::query()->findOrFail($failedOrderId);
        $failedOrder->update(['payment_status' => 'failed']);

        $this->assertDatabaseHas('voucher_redemptions', [
            'order_id' => $failedOrderId,
            'status' => VoucherRedemption::STATUS_REVERSED,
        ]);
        $this->assertSame(1, (int) $voucher->fresh()->usage_count);
    }
}
