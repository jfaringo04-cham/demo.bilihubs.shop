<?php

namespace Tests\Feature;

use App\Models\User;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Schema;

use Tests\TestCase;

class BuyNowCheckoutTest extends TestCase

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

                'CheckoutStockTest requires SQLite in-memory testing.'

            );
        }

        $this->createTestSchema();

        Http::fake([

            'nominatim.openstreetmap.org/*' => Http::response([], 200),

            'maps.googleapis.com/*' => Http::response([], 200),

        ]);
    }

    private function createTestSchema(): void

    {

        Schema::create('users', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table->string('email')->unique();

            $table->string('password');

            $table->string('status')->default('pending');

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

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

        Schema::create('sellers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->nullable();

            $table->string('name');

            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {

            $table->id();

            $table->foreignId('seller_id');
            $table->foreignId('user_id')->nullable();
            $table->string('status')->default('published');
              $table->string('compliance_status')->default('approved');

            $table->string('name');

            $table->unsignedBigInteger('price_minor');

            $table->integer('stock');

            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_id');

            $table->string('name');

            $table->unsignedBigInteger('price_minor');

            $table->integer('stock');

            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table->timestamps();
        });

        Schema::create('product_size', function (Blueprint $table) {

            $table->foreignId('product_id');

            $table->foreignId('size_id');

            $table->integer('stock');
        });

        Schema::create('cart_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id');

            $table->foreignId('product_id');

            $table->foreignId('size_id')->nullable();

            $table->foreignId('variant_id')->nullable();

            $table->integer('quantity');

            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id');

            $table->string('order_number');

            $table->string('reference')->nullable();

            $table->string('status');

            $table->unsignedBigInteger('subtotal_minor');

            $table->unsignedBigInteger('tax_minor');

            $table->unsignedBigInteger('shipping_minor');

            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('seller_discount_minor')->default(0);
            $table->unsignedBigInteger('platform_discount_minor')->default(0);

            $table->text('shipping_address');

            $table->text('notes')->nullable();

            $table->timestamp('ordered_at')->nullable();

            $table->string('payment_method');

            $table->string('payment_status');

            $table->decimal('customer_latitude')->nullable();

            $table->decimal('customer_longitude')->nullable();

            $table->string('delivery_zone')->nullable();

            $table->timestamps();
        });

        Schema::create('seller_orders', function (Blueprint $table) {

            $table->id();

            $table->foreignId('order_id');

            $table->foreignId('seller_id');

            $table->string('status');

            $table->unsignedBigInteger('subtotal_minor');

            $table->unsignedBigInteger('shipping_minor');

            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('seller_discount_minor')->default(0);
            $table->unsignedBigInteger('platform_discount_minor')->default(0);

            $table->timestamps();
        });

        // Test-only logistics hub schema used by HubAssignmentService.

        Schema::create('hubs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('logistic_id')->nullable();

            $table->string('name');

            $table->string('status')->default('active');

            $table->decimal('latitude', 10, 7)->nullable();

            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('seller_order_id')->nullable()->unique();

            $table->foreignId('logistic_id')->nullable();

            $table->foreignId('hub_id')->nullable();

            $table->foreignId('origin_hub_id')->nullable();

            $table->foreignId('destination_hub_id')->nullable();

            $table->string('routing_status')->default('awaiting_pickup');

            $table->timestamp('origin_hub_received_at')->nullable();

            $table->timestamp('transfer_dispatched_at')->nullable();

            $table->timestamp('destination_hub_received_at')->nullable();

            $table->foreignId('rider_id')->nullable();

            $table->string('tracking_number')->unique();

            $table->string('tracking_code')->nullable();

            $table->string('qr_token')->nullable()->unique();

            $table->string('status')->default('pending');

            $table->text('pickup_address')->nullable();

            $table->text('delivery_address')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('seller_order_id');

            $table->foreignId('product_id');

            $table->foreignId('variant_id')->nullable();

            $table->foreignId('size_id')->nullable();

            $table->string('product_name');

            $table->unsignedBigInteger('price_minor');

            $table->integer('quantity');

            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('seller_discount_minor')->default(0);
            $table->unsignedBigInteger('platform_discount_minor')->default(0);

            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id');

            $table->string('title');

            $table->text('message');

            $table->string('type');

            $table->string('link')->nullable();

            $table->timestamps();
        });
    }

    private function buyer(): User

    {

        $user = new User();

        $user->name = 'Checkout Test Buyer';

        $user->email = 'buyer@example.test';

        $user->password = bcrypt('password');

        $user->status = User::STATUS_ACTIVE;

        $user->approved_at = now();
        $user->email_verified_at = now();

        $user->save();

        $roleId = DB::table('roles')->insertGetId([

            'name' => 'buyer',

            'created_at' => now(),

            'updated_at' => now(),

        ]);

        DB::table('role_user')->insert([

            'user_id' => $user->id,

            'role_id' => $roleId,

        ]);

        $user = $user->fresh();

        $this->assertTrue(

            $user->isCustomer(),

            'Test buyer must have the buyer role.'

        );

        $this->assertTrue(

            $user->isActive(),

            'Test buyer must have active status.'

        );

        return $user;
    }

    private function addProduct(

        int $sellerId,

        string $name,

        int $stock,

        int $quantity

    ): int {

        $productId = DB::table('products')->insertGetId([

            'seller_id' => $sellerId,

            'name' => $name,

            'price_minor' => 10000,

            'stock' => $stock,
            'status' => 'published',
              'compliance_status' => 'approved',

            'created_at' => now(),

            'updated_at' => now(),

        ]);

        DB::table('cart_items')->insert([

            'user_id' => auth()->id(),

            'product_id' => $productId,

            'quantity' => $quantity,

            'created_at' => now(),

            'updated_at' => now(),

        ]);

        return $productId;
    }

    private function seller(string $name): int

    {

        return DB::table('sellers')->insertGetId([

            'name' => $name,

            'created_at' => now(),

            'updated_at' => now(),

        ]);
    }

    private function checkout()

    {

        return $this->post('/checkout', [

            'shipping_address' => 'Santa Cruz, Laguna, Philippines',

            'payment_method' => 'cod',

            'contact_name' => 'Checkout Test Buyer',

            'contact_phone' => '09123456789',

        ]);
    }


    private function makeVariant(int $productId, int $stock): int
    {
        return DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'name' => 'Black',
            'price_minor' => 12000,
            'stock' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeSize(int $productId, int $stock): int
    {
        $id = DB::table('sizes')->insertGetId([
            'name' => 'Medium',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_size')->insert([
            'product_id' => $productId,
            'size_id' => $id,
            'stock' => $stock,
        ]);
        return $id;
    }

    private function selectBuyNow(int $productId, int $quantity, ?int $variantId = null, ?int $sizeId = null): void
    {
        $this->withSession(['checkout.buy_now' => [
            'product_id' => $productId,
            'quantity' => $quantity,
            'variant_id' => $variantId,
            'size_id' => $sizeId,
        ]]);
    }

    public function test_buy_now_places_only_selected_product_and_preserves_existing_cart(): void
    {
        $this->actingAs($this->buyer());
        $seller = $this->seller('Shop A');
        $selected = $this->addProduct($seller, 'Buy Now Shirt', 10, 1);
        $other = $this->addProduct($seller, 'Saved Cart Shoes', 8, 2);
        $this->selectBuyNow($selected, 3);

        $this->checkout()->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('order_items', ['product_id' => $selected, 'quantity' => 3]);
        $this->assertDatabaseMissing('order_items', ['product_id' => $other]);
        $this->assertDatabaseHas('products', ['id' => $selected, 'stock' => 7]);
        $this->assertDatabaseHas('products', ['id' => $other, 'stock' => 8]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $selected, 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $other, 'quantity' => 2]);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertDatabaseCount('shipments', 1);
        $this->assertFalse(session()->has('checkout.buy_now'));
    }

    public function test_buy_now_deducts_variant_and_size_stock_without_touching_cart(): void
    {
        $this->actingAs($this->buyer());
        $product = $this->addProduct($this->seller('Shop A'), 'Sized Shirt', 10, 1);
        $variant = $this->makeVariant($product, 6);
        $size = $this->makeSize($product, 5);
        $this->selectBuyNow($product, 2, $variant, $size);

        $this->checkout()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product,
            'variant_id' => $variant,
            'size_id' => $size,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product, 'stock' => 8]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant, 'stock' => 4]);
        $this->assertDatabaseHas('product_size', ['product_id' => $product, 'size_id' => $size, 'stock' => 3]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product, 'quantity' => 1]);
    }

    public function test_buy_now_insufficient_stock_rolls_back_and_preserves_cart_and_session(): void
    {
        $this->actingAs($this->buyer());
        $product = $this->addProduct($this->seller('Shop A'), 'Limited Shirt', 2, 1);
        $this->selectBuyNow($product, 3);

        $this->checkout()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('seller_orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseHas('products', ['id' => $product, 'stock' => 2]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product, 'quantity' => 1]);
        $this->assertTrue(session()->has('checkout.buy_now'));
    }

    public function test_buy_now_invalid_variant_does_not_place_order(): void
    {
        $this->actingAs($this->buyer());
        $product = $this->addProduct($this->seller('Shop A'), 'Shirt', 10, 1);
        $other = $this->addProduct($this->seller('Shop B'), 'Other Shirt', 10, 1);
        $otherVariant = $this->makeVariant($other, 5);
        $this->selectBuyNow($product, 1, $otherVariant);

        $this->checkout()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 2);
    }
}
