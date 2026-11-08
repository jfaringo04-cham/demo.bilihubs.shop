<?php



namespace Tests\Feature;



use App\Models\Order;

use App\Models\Role;

use App\Models\Seller;

use App\Models\SellerOrder;

use App\Models\User;

use App\Models\Logistic;

use App\Models\Shipment;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;

use Tests\SQLiteTestCase;



class SellerRescheduleTest extends SQLiteTestCase

{

    protected function setUp(): void

    {

        parent::setUp();



        // Stop before any schema changes if the connection is unsafe.

        if (

            ! app()->environment('testing') ||

            config('database.default') !== 'sqlite' ||

            config('database.connections.sqlite.database') !== ':memory:' ||

            DB::connection()->getDriverName() !== 'sqlite' ||

            DB::connection()->getDatabaseName() !== ':memory:'

        ) {

            throw new \RuntimeException(

                'SellerRescheduleTest requires SQLite in-memory testing.'

            );
        }



        $this->createTestSchema();
    }



    private function createTestSchema(): void

    {

        Schema::create('users', function (Blueprint $table) {

            $table->id();

            $table->string('name');

            $table->string('email')->unique();

            $table->timestamp('email_verified_at')->nullable();

            $table->string('password');

            $table->string('status')->default('active');

            $table->rememberToken();

            $table->timestamps();
        });



        Schema::create('roles', function (Blueprint $table) {

            $table->id();

            $table->string('name')->unique();

            $table->timestamps();
        });



        Schema::create('role_user', function (Blueprint $table) {

            $table->unsignedBigInteger('role_id');

            $table->unsignedBigInteger('user_id');

            $table->primary(['role_id', 'user_id']);
        });



        Schema::create('sellers', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('name');

            $table->string('slug')->unique();

            $table->string('status')->default('approved');

            $table->timestamps();
        });



        Schema::create('logistics', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('owner_user_id');

            $table->string('company_name');

            $table->string('status')->default('approved');

            $table->timestamps();
        });





        Schema::create('shipments', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('logistic_id')->nullable();

            $table->unsignedBigInteger('seller_order_id')->nullable();

            $table->string('tracking_number')->unique();

            $table->string('status')->default('pending');

            $table->timestamp('failed_returned_to_sorting_center_at')->nullable();

            $table->timestamp('failed_return_received_at')->nullable();

            $table->string('qr_token')->nullable();

            $table->timestamps();
        });





        Schema::create('orders', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('order_number')->unique();

            $table->string('status')->default('placed');

            $table->timestamps();
        });



        Schema::create('seller_orders', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('order_id');

            $table->unsignedBigInteger('seller_id');

            $table->string('status')->default('pending');

            $table->string('reschedule_status')->default('none');

            $table->text('reschedule_reason')->nullable();

            $table->timestamp('reschedule_requested_at')->nullable();

            $table->timestamp('rescheduled_at')->nullable();

            $table->timestamps();



            $table->unique(['order_id', 'seller_id']);
        });



        Schema::create('notifications', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('title');

            $table->text('message')->nullable();

            $table->string('type')->nullable();

            $table->string('link')->nullable();

            $table->boolean('is_read')->default(false);

            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }



    public function test_buyer_reschedule_affects_only_selected_seller_order(): void

    {

        $buyerRole = Role::create(['name' => 'buyer']);

        $sellerRole = Role::create(['name' => 'seller']);



        $buyer = User::create([

            'name' => 'Test Buyer',

            'email' => 'buyer-reschedule@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);

        $buyer->email_verified_at = now();
        $buyer->save();



        $sellerAUser = User::create([

            'name' => 'Seller A',

            'email' => 'seller-a-reschedule@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $sellerBUser = User::create([

            'name' => 'Seller B',

            'email' => 'seller-b-reschedule@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $buyer->roles()->attach($buyerRole->id);

        $sellerAUser->roles()->attach($sellerRole->id);

        $sellerBUser->roles()->attach($sellerRole->id);



        // Refresh the users and their roles after attaching role records.

        $buyer = $buyer->fresh();

        $sellerAUser = $sellerAUser->fresh();

        $sellerBUser = $sellerBUser->fresh();



        $buyer->load('roles');

        $sellerAUser->load('roles');

        $sellerBUser->load('roles');



        $this->assertTrue(

            $buyer->fresh()->isCustomer(),

            'The test buyer must have the buyer role.'

        );



        $sellerA = Seller::create([

            'user_id' => $sellerAUser->id,

            'name' => 'Test Shop A',

            'slug' => 'test-shop-a',

            'status' => 'approved',

        ]);



        $sellerB = Seller::create([

            'user_id' => $sellerBUser->id,

            'name' => 'Test Shop B',

            'slug' => 'test-shop-b',

            'status' => 'approved',

        ]);



        $order = Order::create([

            'user_id' => $buyer->id,

            'order_number' => 'TEST-RESCHEDULE-001',

            'status' => 'delivery_failed',

        ]);



        $sellerOrderA = SellerOrder::create([

            'order_id' => $order->id,

            'seller_id' => $sellerA->id,

            'status' => 'delivery_failed',

            'reschedule_status' => 'none',

        ]);



        $sellerOrderB = SellerOrder::create([

            'order_id' => $order->id,

            'seller_id' => $sellerB->id,

            'status' => 'delivered',

            'reschedule_status' => 'none',

        ]);



        $this->be($buyer, 'web');



        $this->assertTrue(

            \Illuminate\Support\Facades\Auth::guard('web')->check(),

            'Buyer must be authenticated using the web guard.'

        );



        $this->assertEquals(

            $buyer->id,

            \Illuminate\Support\Facades\Auth::guard('web')->id(),

            'Authenticated user must match the test buyer.'

        );



        $this->assertTrue(

            \Illuminate\Support\Facades\Auth::guard('web')->user()->isCustomer(),

            'Authenticated buyer must pass isCustomer().'

        );



        $response = $this->actingAs($buyer)->post(

            route('orders.reschedule', $order),

            [

                'seller_order_id' => $sellerOrderA->id,

                'reschedule_reason' => 'Please deliver again.',

            ]

        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('seller_orders', [

            'id' => $sellerOrderA->id,

            'status' => 'delivery_failed',

            'reschedule_status' => 'requested',

            'reschedule_reason' => 'Please deliver again.',

        ]);



        $this->assertDatabaseHas('seller_orders', [

            'id' => $sellerOrderB->id,

            'status' => 'delivered',

            'reschedule_status' => 'none',

        ]);



        $this->assertDatabaseHas('orders', [

            'id' => $order->id,

            'status' => 'delivery_failed',

        ]);



        $this->assertDatabaseHas('notifications', [

            'user_id' => $buyer->id,

            'title' => 'Reschedule Requested',

        ]);



        $this->assertDatabaseHas('notifications', [

            'user_id' => $sellerAUser->id,

            'title' => 'Reschedule Requested',

        ]);



        $this->assertDatabaseMissing('notifications', [

            'user_id' => $sellerBUser->id,

            'title' => 'Reschedule Requested',

        ]);
    }





    public function test_seller_rejects_only_their_own_pending_reschedule(): void

    {

        $sellerRole = Role::create(['name' => 'seller']);



        $sellerUser = User::create([

            'name' => 'Seller A',

            'email' => 'seller-reject@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $otherSellerUser = User::create([

            'name' => 'Seller B',

            'email' => 'seller-b-reject@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $sellerUser->roles()->attach($sellerRole->id);

        $otherSellerUser->roles()->attach($sellerRole->id);



        $sellerUser = $sellerUser->fresh();

        $otherSellerUser = $otherSellerUser->fresh();



        $sellerUser->load('roles');

        $otherSellerUser->load('roles');



        $this->assertTrue(

            $sellerUser->isSeller(),

            'Test seller must have the seller role.'

        );



        $seller = Seller::create([

            'user_id' => $sellerUser->id,

            'name' => 'Test Shop Reject',

            'slug' => 'test-shop-reject',

            'status' => 'approved',

        ]);



        $otherSeller = Seller::create([

            'user_id' => $otherSellerUser->id,

            'name' => 'Other Test Shop',

            'slug' => 'other-test-shop',

            'status' => 'approved',

        ]);



        $buyer = User::create([

            'name' => 'Buyer',

            'email' => 'buyer-reject@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $order = Order::create([

            'user_id' => $buyer->id,

            'order_number' => 'TEST-REJECT-001',

            'status' => 'delivery_failed',

        ]);



        $sellerOrder = SellerOrder::create([

            'order_id' => $order->id,

            'seller_id' => $seller->id,

            'status' => 'delivery_failed',

            'reschedule_status' => 'requested',

        ]);



        $otherSellerOrder = SellerOrder::create([

            'order_id' => $order->id,

            'seller_id' => $otherSeller->id,

            'status' => 'delivery_failed',

            'reschedule_status' => 'requested',

        ]);









        $response = $this->actingAs($sellerUser, 'web')

            ->post(route('seller.orders.rejectReschedule', $order), [

                'seller_order_id' => $sellerOrder->id,

            ]);



        $response->assertSessionHas('success');



        $this->assertDatabaseHas('seller_orders', [

            'id' => $sellerOrder->id,

            'reschedule_status' => 'rejected',

        ]);



        $this->assertDatabaseHas('seller_orders', [

            'id' => $otherSellerOrder->id,

            'reschedule_status' => 'requested',

        ]);



        $this->assertDatabaseHas('notifications', [

            'user_id' => $buyer->id,

            'title' => 'Reschedule Rejected',

        ]);
    }



    public function test_seller_approves_only_their_own_eligible_reschedule(): void

    {

        $sellerRole = Role::firstOrCreate(['name' => 'seller']);



        $sellerUser = User::create([

            'name' => 'Seller Approval',

            'email' => 'seller-approval@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $otherSellerUser = User::create([

            'name' => 'Other Seller Approval',

            'email' => 'other-seller-approval@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $buyer = User::create([

            'name' => 'Buyer Approval',

            'email' => 'buyer-approval@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $logisticOwner = User::create([

            'name' => 'Logistics Owner',

            'email' => 'logistics-approval@example.test',

            'password' => 'password',

            'status' => 'active',

        ]);



        $sellerUser->roles()->attach($sellerRole->id);

        $otherSellerUser->roles()->attach($sellerRole->id);

        $sellerUser = $sellerUser->fresh();
        $sellerUser->load('roles');
        $this->assertTrue($sellerUser->isSeller());



        $seller = Seller::create([

            'user_id' => $sellerUser->id,

            'name' => 'Approval Test Shop',

            'slug' => 'approval-test-shop',

            'status' => 'approved',

        ]);



        $otherSeller = Seller::create([

            'user_id' => $otherSellerUser->id,

            'name' => 'Other Approval Shop',

            'slug' => 'other-approval-shop',

            'status' => 'approved',

        ]);



        $logistic = Logistic::create([

            'owner_user_id' => $logisticOwner->id,

            'company_name' => 'Test Logistics',

            'status' => 'approved',

        ]);



        $order = Order::create([

            'user_id' => $buyer->id,

            'order_number' => 'TEST-APPROVE-001',

            'status' => 'delivery_failed',

        ]);



        $sellerOrder = SellerOrder::create([

            'order_id' => $order->id,

            'seller_id' => $seller->id,

            'status' => 'delivery_failed',

            'reschedule_status' => 'requested',

        ]);



        $otherSellerOrder = SellerOrder::create([

            'order_id' => $order->id,

            'seller_id' => $otherSeller->id,

            'status' => 'delivery_failed',

            'reschedule_status' => 'requested',

        ]);



        Shipment::create([

            'logistic_id' => $logistic->id,

            'seller_order_id' => $sellerOrder->id,

            'tracking_number' => 'TEST-APPROVE-SHIP-001',

            'status' => 'failed_return_at_sorting_center',

            'failed_return_received_at' => now(),

        ]);



        $response = $this->actingAs($sellerUser, 'web')

            ->post(route('seller.orders.approveReschedule', $order), [

                'seller_order_id' => $sellerOrder->id,

            ]);


        $response->assertSessionHas(

            'success',

            'Reschedule approved for this seller order.'

        );



        $this->assertDatabaseHas('seller_orders', [

            'id' => $sellerOrder->id,

            'reschedule_status' => 'approved',

        ]);



        $this->assertNotNull(

            SellerOrder::findOrFail($sellerOrder->id)->rescheduled_at

        );



        $this->assertDatabaseHas('seller_orders', [

            'id' => $otherSellerOrder->id,

            'reschedule_status' => 'requested',

        ]);



        $this->assertDatabaseHas('notifications', [

            'user_id' => $buyer->id,

            'title' => 'Reschedule Approved',

        ]);



        $this->assertDatabaseHas('notifications', [

            'user_id' => $logisticOwner->id,

            'title' => 'Rescheduled Parcel Ready for Assignment',

        ]);
    }
}
