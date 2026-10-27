<?php

namespace Tests\Feature;

use App\Models\User;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Schema;

use Tests\TestCase;

class CheckoutStockTest extends TestCase

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
              'status' => 'published',
              'compliance_status' => 'approved',

            'name' => $name,

            'price_minor' => 10000,

            'stock' => $stock,

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

    public function test_successful_checkout_deducts_stock(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct(

            $this->seller('Shop A'),

            'Test Shirt',

            10,

            3

        );

        // Temporarily expose the original exception causing HTTP 500.

        $this->withoutExceptionHandling();

        $response = $this->checkout();

        $response->assertStatus(302);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);

        $this->assertDatabaseCount('seller_orders', 1);

        $this->assertDatabaseCount('order_items', 1);

        $this->assertDatabaseHas('products', [

            'id' => $productId,

            'stock' => 7,

        ]);
    }

    public function test_insufficient_product_stock_rolls_back_checkout(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct(

            $this->seller('Shop A'),

            'Test Shirt',

            2,

            5

        );

        $response = $this->checkout();

        $response->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseCount('seller_orders', 0);

        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [

            'id' => $productId,

            'stock' => 2,

        ]);
    }

    public function test_multi_seller_checkout_creates_separate_seller_orders(): void

    {

        $this->actingAs($this->buyer());

        $sellerA = $this->seller('Shop A');

        $sellerB = $this->seller('Shop B');

        $this->addProduct($sellerA, 'Shirt', 10, 2);

        $this->addProduct($sellerB, 'Shoes', 10, 1);

        $response = $this->checkout();

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);

        $this->assertDatabaseCount('seller_orders', 2);

        $this->assertDatabaseCount('order_items', 2);

        $this->assertEquals(

            2,

            DB::table('seller_orders')->distinct('seller_id')->count('seller_id')

        );

        // Shipping is charged per seller/shipment: PHP 50.00 each.
        $this->assertSame(10000, (int) DB::table('orders')->value('shipping_minor'));
        $this->assertSame(10000, (int) DB::table('seller_orders')->sum('shipping_minor'));

        foreach (DB::table('seller_orders')->pluck('shipping_minor') as $sellerShippingMinor) {
            $this->assertSame(5000, (int) $sellerShippingMinor);
        }
    }

    public function test_multi_seller_out_of_stock_rolls_back_everything(): void

    {

        $this->actingAs($this->buyer());

        $productA = $this->addProduct(

            $this->seller('Shop A'),

            'Shirt',

            10,

            2

        );

        $productB = $this->addProduct(

            $this->seller('Shop B'),

            'Shoes',

            1,

            3

        );

        $response = $this->checkout();

        $response->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseCount('seller_orders', 0);

        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [

            'id' => $productA,

            'stock' => 10,

        ]);

        $this->assertDatabaseHas('products', [

            'id' => $productB,

            'stock' => 1,

        ]);
    }

    private function addVariant(

        int $productId,

        string $name,

        int $stock

    ): int {

        return DB::table('product_variants')->insertGetId([

            'product_id' => $productId,

            'name' => $name,

            'price_minor' => 10000,

            'stock' => $stock,

            'created_at' => now(),

            'updated_at' => now(),

        ]);
    }

    private function addSize(

        int $productId,

        string $name,

        int $stock

    ): int {

        $sizeId = DB::table('sizes')->insertGetId([

            'name' => $name,

            'created_at' => now(),

            'updated_at' => now(),

        ]);

        DB::table('product_size')->insert([

            'product_id' => $productId,

            'size_id' => $sizeId,

            'stock' => $stock,

        ]);

        return $sizeId;
    }

    public function test_variant_stock_is_deducted(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct(

            $this->seller('Shop A'),

            'Black Shirt',

            10,

            3

        );

        $variantId = $this->addVariant(

            $productId,

            'Black / Medium',

            8

        );

        DB::table('cart_items')

            ->where('product_id', $productId)

            ->update(['variant_id' => $variantId]);

        $this->checkout()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [

            'id' => $productId,

            'stock' => 7,

        ]);

        $this->assertDatabaseHas('product_variants', [

            'id' => $variantId,

            'stock' => 5,

        ]);

        $this->assertDatabaseHas('order_items', [

            'product_id' => $productId,

            'variant_id' => $variantId,

            'quantity' => 3,

        ]);
    }

    public function test_insufficient_variant_stock_rejects_checkout(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct(

            $this->seller('Shop A'),

            'Black Shirt',

            10,

            4

        );

        $variantId = $this->addVariant(

            $productId,

            'Black / Medium',

            2

        );

        DB::table('cart_items')

            ->where('product_id', $productId)

            ->update(['variant_id' => $variantId]);

        $this->checkout()->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseCount('seller_orders', 0);

        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [

            'id' => $productId,

            'stock' => 10,

        ]);

        $this->assertDatabaseHas('product_variants', [

            'id' => $variantId,

            'stock' => 2,

        ]);
    }

    public function test_size_stock_is_deducted(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct(

            $this->seller('Shop A'),

            'Cotton Shirt',

            10,

            3

        );

        $sizeId = $this->addSize(

            $productId,

            'Medium',

            7

        );

        DB::table('cart_items')

            ->where('product_id', $productId)

            ->update(['size_id' => $sizeId]);

        $this->checkout()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [

            'id' => $productId,

            'stock' => 7,

        ]);

        $this->assertDatabaseHas('product_size', [

            'product_id' => $productId,

            'size_id' => $sizeId,

            'stock' => 4,

        ]);

        $this->assertDatabaseHas('order_items', [

            'product_id' => $productId,

            'size_id' => $sizeId,

            'quantity' => 3,

        ]);
    }

    public function test_insufficient_size_stock_rejects_checkout(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct(

            $this->seller('Shop A'),

            'Cotton Shirt',

            10,

            4

        );

        $sizeId = $this->addSize(

            $productId,

            'Medium',

            2

        );

        DB::table('cart_items')

            ->where('product_id', $productId)

            ->update(['size_id' => $sizeId]);

        $this->checkout()->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseCount('seller_orders', 0);

        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [

            'id' => $productId,

            'stock' => 10,

        ]);

        $this->assertDatabaseHas('product_size', [

            'product_id' => $productId,

            'size_id' => $sizeId,

            'stock' => 2,

        ]);
    }

    public function test_multi_seller_variant_failure_rolls_back_everything(): void

    {

        $this->actingAs($this->buyer());

        $productA = $this->addProduct(

            $this->seller('Shop A'),

            'Shirt',

            10,

            2

        );

        $productB = $this->addProduct(

            $this->seller('Shop B'),

            'Shoes',

            10,

            4

        );

        $variantA = $this->addVariant(

            $productA,

            'Black',

            8

        );

        $variantB = $this->addVariant(

            $productB,

            'White',

            1

        );

        DB::table('cart_items')

            ->where('product_id', $productA)

            ->update(['variant_id' => $variantA]);

        DB::table('cart_items')

            ->where('product_id', $productB)

            ->update(['variant_id' => $variantB]);

        $this->checkout()->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseCount('seller_orders', 0);

        $this->assertDatabaseCount('order_items', 0);

        $this->assertDatabaseHas('products', [

            'id' => $productA,

            'stock' => 10,

        ]);

        $this->assertDatabaseHas('products', [

            'id' => $productB,

            'stock' => 10,

        ]);

        $this->assertDatabaseHas('product_variants', [

            'id' => $variantA,

            'stock' => 8,

        ]);

        $this->assertDatabaseHas('product_variants', [

            'id' => $variantB,

            'stock' => 1,

        ]);
    }

    public function test_successful_checkout_creates_one_shipment_per_seller_order(): void

    {

        $this->actingAs($this->buyer());

        $this->addProduct(

            $this->seller('Shop A'),

            'Test Shirt',

            10,

            1

        );

        $this->checkout()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);

        $this->assertDatabaseCount('seller_orders', 1);

        $this->assertDatabaseCount('shipments', 1);

        $sellerOrderId = DB::table('seller_orders')->value('id');

        $this->assertDatabaseHas('shipments', [

            'seller_order_id' => $sellerOrderId,

        ]);
    }

    public function test_multi_seller_checkout_creates_one_shipment_for_each_seller(): void

    {

        $this->actingAs($this->buyer());

        $this->addProduct($this->seller('Shop A'), 'Shirt', 10, 1);

        $this->addProduct($this->seller('Shop B'), 'Shoes', 10, 1);

        $this->checkout()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('seller_orders', 2);

        $this->assertDatabaseCount('shipments', 2);

        $sellerOrderIds = DB::table('seller_orders')->pluck('id');

        foreach ($sellerOrderIds as $sellerOrderId) {

            $this->assertSame(

                1,

                DB::table('shipments')

                    ->where('seller_order_id', $sellerOrderId)

                    ->count()

            );
        }
    }

    public function test_failed_checkout_does_not_create_shipments(): void

    {

        $this->actingAs($this->buyer());

        $this->addProduct(

            $this->seller('Shop A'),

            'Test Shirt',

            1,

            5

        );

        $this->checkout()->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);

        $this->assertDatabaseCount('seller_orders', 0);

        $this->assertDatabaseCount('shipments', 0);
    }


    public function test_repeated_shipment_creation_does_not_duplicate_parcel(): void
    {
        $this->actingAs($this->buyer());

        $this->addProduct(
            $this->seller('Shop A'),
            'Test Shirt',
            10,
            1
        );

        $this->checkout()->assertSessionHasNoErrors();

        $order = \App\Models\Order::firstOrFail();

        $originalShipment = DB::table('shipments')->first();

        $this->assertNotNull($originalShipment);

        $service = app(\App\Services\ShipmentCreationService::class);

        $service->createForOrder($order);
        $service->createForOrder($order);

        $this->assertDatabaseCount('shipments', 1);

        $shipment = DB::table('shipments')->first();

        $this->assertSame($originalShipment->id, $shipment->id);
        $this->assertSame(
            $originalShipment->tracking_number,
            $shipment->tracking_number
        );
    }

    public function test_repeated_creation_preserves_existing_shipment_data(): void
    {
        $this->actingAs($this->buyer());

        $this->addProduct(
            $this->seller('Shop A'),
            'Test Shirt',
            10,
            1
        );

        $this->checkout()->assertSessionHasNoErrors();

        $order = \App\Models\Order::firstOrFail();

        $shipmentId = DB::table('shipments')->value('id');

        DB::table('shipments')
            ->where('id', $shipmentId)
            ->update([
                'status' => 'in_transit',
                'rider_id' => 77,
                'hub_id' => 88,
                'tracking_number' => 'SPE-EXISTING123',
            ]);

        app(\App\Services\ShipmentCreationService::class)
            ->createForOrder($order);

        $this->assertDatabaseCount('shipments', 1);

        $this->assertDatabaseHas('shipments', [
            'id' => $shipmentId,
            'status' => 'in_transit',
            'rider_id' => 77,
            'hub_id' => 88,
            'tracking_number' => 'SPE-EXISTING123',
        ]);
    }

    public function test_repeated_multi_seller_creation_preserves_one_parcel_each(): void
    {
        $this->actingAs($this->buyer());

        $this->addProduct(
            $this->seller('Shop A'),
            'Shirt',
            10,
            1
        );

        $this->addProduct(
            $this->seller('Shop B'),
            'Shoes',
            10,
            1
        );

        $this->checkout()->assertSessionHasNoErrors();

        $order = \App\Models\Order::firstOrFail();

        $before = DB::table('shipments')
            ->orderBy('seller_order_id')
            ->pluck('tracking_number', 'seller_order_id')
            ->all();

        app(\App\Services\ShipmentCreationService::class)
            ->createForOrder($order);

        $after = DB::table('shipments')
            ->orderBy('seller_order_id')
            ->pluck('tracking_number', 'seller_order_id')
            ->all();

        $this->assertDatabaseCount('shipments', 2);
        $this->assertSame($before, $after);
    }
}
