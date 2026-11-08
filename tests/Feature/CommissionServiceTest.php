<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use App\Models\Product;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createSellerOrder(
        Order $order,
        User $sellerUser,
        string $status,
        int $subtotalMinor
    ): SellerOrder {
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Test Shop ' . $sellerUser->id,
            'slug' => 'test-shop-' . $sellerUser->id,
            'status' => 'approved',
            'commission_bps' => 0,
        ]);

        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'status' => $status,
            'subtotal_minor' => $subtotalMinor,
            'shipping_minor' => 0,
            'total_minor' => $subtotalMinor,
        ]);

        // Create a product for the test order item.
        // Use DB insert to supply both legacy price and minor-unit price.
        $productId = DB::table('products')->insertGetId([
            'name' => 'Test Product',
            'price_minor' => $subtotalMinor,
            'image' => 'test-product.jpg',
            'alt_text' => 'Test Product Image',
            'user_id' => $sellerUser->id,
            'seller_id' => $seller->id,
            'stock' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        OrderItem::create([
            'seller_order_id' => $sellerOrder->id,
            'product_id' => $productId,
            'product_name' => 'Test Product',
            'price_minor' => $subtotalMinor,
            'quantity' => 1,
            'subtotal_minor' => $subtotalMinor,
        ]);

        return $sellerOrder;
    }

    public function test_delivered_seller_order_generates_ten_percent_commission(): void
    {
        $buyer = User::factory()->create();
        $sellerUser = User::factory()->create();

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'TEST-COMMISSION-001',
            'reference' => 'TEST-COMMISSION-001',
            'status' => 'delivered',
            'shipping_address' => [
                'name' => 'Test Buyer',
                'phone' => '09123456789',
                'address_line1' => 'Test Street',
                'city' => 'Santa Cruz',
                'province' => 'Laguna',
                'postal_code' => '4009',
                'country' => 'Philippines',
            ],
        ]);

        $this->createSellerOrder(
            $order,
            $sellerUser,
            'delivered',
            100000
        );

        app(CommissionService::class)->generateForOrder($order);

        $this->assertDatabaseHas('commissions', [
            'order_id' => $order->id,
            'seller_id' => $sellerUser->id,
            'order_total_minor' => 100000,
            'amount_minor' => 10000,
            'status' => 'pending',
        ]);
    }

    public function test_pending_seller_order_does_not_generate_commission(): void
    {
        $buyer = User::factory()->create();
        $sellerUser = User::factory()->create();

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'TEST-COMMISSION-002',
            'reference' => 'TEST-COMMISSION-002',
            'status' => 'delivered',
            'shipping_address' => [
                'name' => 'Test Buyer',
                'phone' => '09123456789',
                'address_line1' => 'Test Street',
                'city' => 'Santa Cruz',
                'province' => 'Laguna',
                'postal_code' => '4009',
                'country' => 'Philippines',
            ],
        ]);

        $this->createSellerOrder(
            $order,
            $sellerUser,
            'pending',
            100000
        );

        app(CommissionService::class)->generateForOrder($order);

        $this->assertDatabaseCount('commissions', 0);
    }

    public function test_two_sellers_generate_separate_commissions(): void
    {
        $buyer = User::factory()->create();
        $sellerA = User::factory()->create();
        $sellerB = User::factory()->create();

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'TEST-COMMISSION-003',
            'reference' => 'TEST-COMMISSION-003',
            'status' => 'delivered',
            'shipping_address' => [
                'name' => 'Test Buyer',
                'phone' => '09123456789',
                'address_line1' => 'Test Street',
                'city' => 'Santa Cruz',
                'province' => 'Laguna',
                'postal_code' => '4009',
                'country' => 'Philippines',
            ],
        ]);

        $this->createSellerOrder($order, $sellerA, 'delivered', 100000);
        $this->createSellerOrder($order, $sellerB, 'delivered', 200000);

        app(CommissionService::class)->generateForOrder($order);

        $this->assertDatabaseCount('commissions', 2);

        $this->assertDatabaseHas('commissions', [
            'seller_id' => $sellerA->id,
            'amount_minor' => 10000,
        ]);

        $this->assertDatabaseHas('commissions', [
            'seller_id' => $sellerB->id,
            'amount_minor' => 20000,
        ]);
    }

    public function test_repeated_generation_does_not_duplicate_commission(): void
    {
        $buyer = User::factory()->create();
        $sellerUser = User::factory()->create();

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'TEST-COMMISSION-004',
            'reference' => 'TEST-COMMISSION-004',
            'status' => 'delivered',
            'shipping_address' => [
                'name' => 'Test Buyer',
                'phone' => '09123456789',
                'address_line1' => 'Test Street',
                'city' => 'Santa Cruz',
                'province' => 'Laguna',
                'postal_code' => '4009',
                'country' => 'Philippines',
            ],
        ]);

        $this->createSellerOrder(
            $order,
            $sellerUser,
            'delivered',
            100000
        );

        $service = app(CommissionService::class);

        $service->generateForOrder($order);
        $service->generateForOrder($order);

        $this->assertDatabaseCount('commissions', 1);
    }

    public function test_only_delivered_seller_receives_commission(): void
    {
        $buyer = User::factory()->create();
        $sellerA = User::factory()->create();
        $sellerB = User::factory()->create();

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'TEST-COMMISSION-005',
            'reference' => 'TEST-COMMISSION-005',
            'status' => 'delivered',
            'shipping_address' => [
                'name' => 'Test Buyer',
                'phone' => '09123456789',
                'address_line1' => 'Test Street',
                'city' => 'Santa Cruz',
                'province' => 'Laguna',
                'postal_code' => '4009',
                'country' => 'Philippines',
            ],
        ]);

        $this->createSellerOrder($order, $sellerA, 'delivered', 100000);
        $this->createSellerOrder($order, $sellerB, 'pending', 200000);

        app(CommissionService::class)->generateForOrder($order);

        $this->assertDatabaseCount('commissions', 1);

        $this->assertDatabaseHas('commissions', [
            'seller_id' => $sellerA->id,
            'amount_minor' => 10000,
        ]);

        $this->assertDatabaseMissing('commissions', [
            'seller_id' => $sellerB->id,
        ]);
    }
}
