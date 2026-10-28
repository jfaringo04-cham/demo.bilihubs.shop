<?php



namespace Tests\Feature;



use App\Models\User;

use App\Models\Order;

use App\Http\Controllers\OrderController;

use Illuminate\Http\Request;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Schema;

use Tests\TestCase;



class OrderCancellationTest extends TestCase

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

                'OrderCancellationTest requires SQLite in-memory testing.'

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



        // Test-only logistics tables required by shipment creation during checkout.
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





    private function placeOrder(): Order
    {
        $this->checkout()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);

        return Order::query()->firstOrFail();
    }



    private function cancelOrder(Order $order, string $reason = 'Changed my mind')

    {

        // Direct controller invocation avoids assuming a particular cancellation route.

        // This tests the controller's database behavior, not route/middleware wiring.

        return app(OrderController::class)->requestCancellation(

            Request::create('/test-cancel', 'POST', ['reason' => $reason]),

            $order

        );
    }



    private function addVariant(int $productId, int $stock): int

    {

        return DB::table('product_variants')->insertGetId([

            'product_id' => $productId,

            'name' => 'Black / Medium',

            'price_minor' => 10000,

            'stock' => $stock,

            'created_at' => now(),

            'updated_at' => now(),

        ]);
    }



    private function addSize(int $productId, int $stock): int

    {

        $sizeId = DB::table('sizes')->insertGetId([

            'name' => 'Medium',

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



    public function test_cancellation_restores_product_variant_and_size_stock(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct($this->seller('Shop A'), 'Shirt', 10, 3);

        $variantId = $this->addVariant($productId, 8);

        $sizeId = $this->addSize($productId, 7);

        DB::table('cart_items')->where('product_id', $productId)

            ->update(['variant_id' => $variantId, 'size_id' => $sizeId]);



        $order = $this->placeOrder();

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 7]);

        $this->assertDatabaseHas('product_variants', ['id' => $variantId, 'stock' => 5]);

        $this->assertDatabaseHas('product_size', ['product_id' => $productId, 'size_id' => $sizeId, 'stock' => 4]);



        $this->cancelOrder($order);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 10]);

        $this->assertDatabaseHas('product_variants', ['id' => $variantId, 'stock' => 8]);

        $this->assertDatabaseHas('product_size', ['product_id' => $productId, 'size_id' => $sizeId, 'stock' => 7]);

        $this->assertSame(1, DB::table('notifications')->where('title', 'Order Cancelled')->count());
    }



    public function test_repeated_cancellation_does_not_restore_stock_twice(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct($this->seller('Shop A'), 'Shirt', 10, 2);

        $order = $this->placeOrder();



        $this->cancelOrder($order);

        $this->cancelOrder($order->fresh());



        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 10]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);

        $this->assertSame(1, DB::table('notifications')->where('title', 'Order Cancelled')->count());
    }



    public function test_delivered_order_cannot_be_cancelled(): void

    {

        $this->actingAs($this->buyer());

        $productId = $this->addProduct($this->seller('Shop A'), 'Shirt', 10, 2);

        $order = $this->placeOrder();

        $order->update(['status' => 'delivered']);



        $this->cancelOrder($order->fresh());

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 8]);

        $this->assertSame(0, DB::table('notifications')->where('title', 'Order Cancelled')->count());
    }



    public function test_other_buyer_cannot_cancel_order(): void

    {

        $buyer = $this->buyer();

        $this->actingAs($buyer);

        $productId = $this->addProduct($this->seller('Shop A'), 'Shirt', 10, 2);

        $order = $this->placeOrder();



        $otherBuyer = User::query()->create([

            'name' => 'Other Buyer',

            'email' => 'other@example.test',

            'password' => bcrypt('password'),

            'status' => User::STATUS_ACTIVE,

        ]);

        $this->actingAs($otherBuyer);



        try {

            $this->cancelOrder($order);

            $this->fail('Expected authorization to reject a different buyer.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {

            $this->assertSame(403, $e->getStatusCode());
        }



        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'placed']);

        $this->assertDatabaseHas('products', ['id' => $productId, 'stock' => 8]);
    }



    public function test_multi_seller_cancellation_restores_all_product_stock(): void

    {

        $this->actingAs($this->buyer());

        $productA = $this->addProduct($this->seller('Shop A'), 'Shirt', 10, 2);

        $productB = $this->addProduct($this->seller('Shop B'), 'Shoes', 6, 3);

        $order = $this->placeOrder();

        $this->assertDatabaseCount('seller_orders', 2);



        $this->cancelOrder($order);

        $this->assertDatabaseHas('products', ['id' => $productA, 'stock' => 10]);

        $this->assertDatabaseHas('products', ['id' => $productB, 'stock' => 6]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }
}
