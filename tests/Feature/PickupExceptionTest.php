<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Hub;
use App\Models\Logistic;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RiderHubAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickupExceptionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(string $status = 'ready_for_pickup'): array
    {
        $buyer = User::factory()->create();
        $sellerOwner = User::factory()->create();
        $owner = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Pickup Exception Express',
            'status' => 'active',
        ]);

        $hub = Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'San Pablo Sur Hub',
            'address' => 'San Pablo Sur, Santa Cruz, Laguna',
            'barangay' => 'San Pablo Sur',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
        ]);

        $address = Address::create([
            'user_id' => $sellerOwner->id,
            'label' => 'Pickup',
            'address_line1' => '123 Seller Street',
            'barangay' => 'San Pablo Sur',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'country' => 'Philippines',
            'is_default' => true,
        ]);

        $seller = Seller::create([
            'user_id' => $sellerOwner->id,
            'name' => 'Pickup Test Seller',
            'slug' => 'pickup-exception-test-seller',
            'status' => 'approved',
            'pickup_address_id' => $address->id,
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-PICKUP-EXCEPTION-001',
            'status' => 'placed',
            'subtotal_minor' => 10000,
            'tax_minor' => 300,
            'shipping_minor' => 5000,
            'total_minor' => 15300,
            'shipping_address' => [
                'recipient' => 'Buyer',
                'address' => 'Bubukal, Santa Cruz, Laguna',
            ],
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);

        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'logistic_id' => $logistic->id,
            'status' => $status,
            'subtotal_minor' => 10000,
            'shipping_minor' => 5000,
            'total_minor' => 15000,
        ]);

        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistic_id' => $logistic->id,
            'hub_id' => $hub->id,
            'origin_hub_id' => $hub->id,
            'destination_hub_id' => $hub->id,
            'routing_status' => 'awaiting_pickup',
            'tracking_number' => 'SPE-EXCEPTION001',
            'status' => 'pending',
            'pickup_address' => 'San Pablo Sur',
            'delivery_address' => 'Bubukal',
        ]);

        // Give the logistics owner the required account role.
        $logisticRole = Role::firstOrCreate(['name' => 'logistics']);

        $owner->roles()->syncWithoutDetaching([
            $logisticRole->id,
        ]);

        // Give the logistics owner the required account role.
        $logisticRole = Role::firstOrCreate(['name' => 'logistics']);

        $owner->roles()->syncWithoutDetaching([
            $logisticRole->id,
        ]);

        $owner->refresh();

        $this->assertTrue(
            $owner->isLogisticOwner(),
            'Test owner must have the logistics role.'
        );

        $this->assertTrue(
            $owner->isActive(),
            'Test owner account must be active.'
        );

        $this->assertEquals(
            $logistic->id,
            $owner->ownedLogistic?->id,
            'Test owner must own the logistics company.'
        );

        return compact('owner', 'logistic', 'hub', 'sellerOrder', 'shipment');
    }

    private function rider(Logistic $logistic, Hub $hub): User
    {
        $role = Role::firstOrCreate(['name' => 'rider']);

        $rider = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'logistic_id' => $logistic->id,
            'logistic_status' => 'approved',
            'availability_status' => 'available',
            'max_capacity' => 10,
            'current_load' => 0,
        ]);

        $rider->roles()->syncWithoutDetaching([$role->id]);

        RiderHubAssignmentService::assign($rider, $hub);

        return $rider->fresh();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('logistic.pickup-exceptions.index'))
            ->assertRedirect();
    }

    public function test_guest_cannot_retry(): void
    {
        $data = $this->scenario();

        $this->post(route(
            'logistic.pickup-exceptions.retry',
            $data['shipment']
        ))->assertRedirect();

        $this->assertDatabaseCount('pickup_requests', 0);
    }

    public function test_owner_can_view_dashboard(): void
    {
        $data = $this->scenario();

        $this->actingAs($data['owner'])
            ->get(route('logistic.pickup-exceptions.index'))
            ->assertOk()
            ->assertViewIs('logistic.pickup-exceptions');
    }

    public function test_other_owner_cannot_retry_foreign_shipment(): void
    {
        $data = $this->scenario();

        $other = User::factory()->create();

        Logistic::create([
            'owner_user_id' => $other->id,
            'company_name' => 'Other Express',
            'status' => 'active',
        ]);

        $this->rider($data['logistic'], $data['hub']);

        $this->actingAs($other)
            ->post(route(
                'logistic.pickup-exceptions.retry',
                $data['shipment']
            ))
            ->assertForbidden();

        $this->assertDatabaseCount('pickup_requests', 0);
    }

    public function test_seller_must_be_ready(): void
    {
        $data = $this->scenario('preparing');

        $this->rider($data['logistic'], $data['hub']);

        $this->actingAs($data['owner'])
            ->from(route('logistic.pickup-exceptions.index'))
            ->post(route(
                'logistic.pickup-exceptions.retry',
                $data['shipment']
            ))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pickup_requests', 0);
    }

    public function test_picked_up_shipment_cannot_retry(): void
    {
        $data = $this->scenario();

        $this->rider($data['logistic'], $data['hub']);

        $data['shipment']->update(['picked_up_at' => now()]);

        $this->actingAs($data['owner'])
            ->from(route('logistic.pickup-exceptions.index'))
            ->post(route(
                'logistic.pickup-exceptions.retry',
                $data['shipment']
            ))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pickup_requests', 0);
    }

    public function test_no_eligible_rider_creates_no_offer(): void
    {
        $data = $this->scenario();

        $this->actingAs($data['owner'])
            ->from(route('logistic.pickup-exceptions.index'))
            ->post(route(
                'logistic.pickup-exceptions.retry',
                $data['shipment']
            ))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pickup_requests', 0);
    }

    public function test_full_capacity_rider_is_not_offered(): void
    {
        $data = $this->scenario();

        $rider = $this->rider($data['logistic'], $data['hub']);

        $rider->update([
            'max_capacity' => 1,
            'current_load' => 1,
        ]);

        $this->actingAs($data['owner'])
            ->from(route('logistic.pickup-exceptions.index'))
            ->post(route(
                'logistic.pickup-exceptions.retry',
                $data['shipment']
            ))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('pickup_requests', 0);
    }

    public function test_eligible_rider_receives_pickup_offer(): void
    {
        $data = $this->scenario();

        $rider = $this->rider($data['logistic'], $data['hub']);

        $this->actingAs($data['owner'])
            ->from(route('logistic.pickup-exceptions.index'))
            ->post(route(
                'logistic.pickup-exceptions.retry',
                $data['shipment']
            ))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pickup_requests', [
            'shipment_id' => $data['shipment']->id,
            'rider_id' => $rider->id,
            'status' => 'offered',
        ]);

        $this->assertNull($data['shipment']->fresh()->picked_up_at);
    }

    public function test_repeated_retry_does_not_duplicate_offer(): void
    {
        $data = $this->scenario();

        $this->rider($data['logistic'], $data['hub']);

        $url = route('logistic.pickup-exceptions.retry', $data['shipment']);

        $this->actingAs($data['owner']);

        $this->from(route('logistic.pickup-exceptions.index'))
            ->post($url)
            ->assertSessionHas('success');

        $this->from(route('logistic.pickup-exceptions.index'))
            ->post($url)
            ->assertRedirect();

        $this->assertSame(
            1,
            PickupRequest::where('shipment_id', $data['shipment']->id)->count()
        );
    }
}