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
use App\Services\PickupAcceptanceService;
use App\Services\PickupExceptionService;
use App\Services\PickupOfferService;
use App\Services\RiderHubAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PickupOfferRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_rider_to_hub_syncs_barangay_zone(): void
    {
        $owner = User::factory()->create();

        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Speedy Express',
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

        $rider = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'logistic_id' => $logistic->id,
            'logistic_status' => 'approved',
            'availability_status' => 'available',
            'assigned_zone' => 'Santa Cruz',
        ]);

        $riderRole = Role::firstOrCreate([
            'name' => 'rider',
        ]);

        $rider->roles()->syncWithoutDetaching([
            $riderRole->id,
        ]);

        RiderHubAssignmentService::assign($rider, $hub);
        $rider->refresh();

        $this->assertSame($hub->id, $rider->hub_id);
        $this->assertSame('San Pablo Sur', $rider->assigned_zone);
    }

    public function test_pending_ready_for_pickup_shipment_is_offered_after_rider_becomes_eligible(): void
    {
        [$logistic, $hub, $shipment, $rider] = $this->pickupFixture();

        $this->assertNull(PickupOfferService::offerNext($shipment));
        $this->assertDatabaseCount('pickup_requests', 0);

        RiderHubAssignmentService::assign($rider, $hub);

        $offered = PickupOfferService::retryPendingForHub($logistic->id, $hub->id);

        $this->assertSame(1, $offered);
        $this->assertDatabaseHas('pickup_requests', [
            'shipment_id' => $shipment->id,
            'rider_id' => $rider->id,
            'status' => 'offered',
        ]);

        $this->assertSame(1, PickupRequest::where('shipment_id', $shipment->id)->count());
    }

    public function test_pickup_weight_applies_to_manual_offers_automatic_offers_and_acceptance(): void
    {
        [$logistic, $hub, $shipment, $motorcycle, $owner] = $this->pickupFixture();
        $logistic->update(['pickup_payload_limits_kg' => ['motorcycle' => 12, 'van' => 30]]);
        $motorcycle->update(['vehicle_type' => 'motorcycle']);
        RiderHubAssignmentService::assign($motorcycle, $hub);
        $shipment->update(['destination_hub_id' => $hub->id]);

        $this->assertNull(PickupOfferService::offerNext($shipment));
        $this->assertSame('pickup_weight_missing', PickupExceptionService::forShipment($shipment->fresh())['code']);

        $this->actingAs($owner)
            ->post(route('logistic.pickup-exceptions.retry', $shipment), ['packed_weight_kg' => 18])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertEquals(18, $shipment->fresh()->estimated_weight_kg);
        $this->assertSame('pickup_weight_capacity', PickupExceptionService::forShipment($shipment->fresh())['code']);

        $this->post(route('logistic.shipments.assign-pickup-rider', $shipment), ['rider_id' => $motorcycle->id])
            ->assertRedirect()->assertSessionHas('error', 'The parcel exceeds this rider vehicle’s pickup weight limit.');
        $this->assertDatabaseCount('pickup_requests', 0);

        $van = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'logistic_id' => $logistic->id,
            'logistic_status' => 'approved',
            'hub_id' => $hub->id,
            'vehicle_type' => 'van',
            'availability_status' => 'available',
            'max_capacity' => 5,
            'current_load' => 0,
        ]);
        $van->roles()->attach(Role::where('name', 'rider')->value('id'));

        $offer = PickupOfferService::offerNext($shipment);
        $this->assertNotNull($offer);
        $this->assertEquals($van->id, $offer->rider_id);

        $otherOrder = $shipment->sellerOrder->order->replicate();
        $otherOrder->order_number = 'ORD-RETRY-002';
        $otherOrder->reference = 'TEST-RETRY-' . uniqid();
        $otherOrder->save();
        $otherSellerOrder = $shipment->sellerOrder->replicate();
        $otherSellerOrder->order_id = $otherOrder->id;
        $otherSellerOrder->save();

        $load = Shipment::create([
            'seller_order_id' => $otherSellerOrder->id,
            'logistic_id' => $logistic->id,
            'hub_id' => $hub->id,
            'origin_hub_id' => $hub->id,
            'pickup_rider_id' => $van->id,
            'estimated_weight_kg' => 12.001,
            'tracking_code' => 'TEST-LOAD-002',
            'tracking_number' => 'SPE-LOAD002',
            'status' => 'assigned',
            'pickup_address' => 'San Pablo Sur',
            'delivery_address' => 'Bubukal',
        ]);

        try {
            PickupAcceptanceService::accept($offer, $van);
            $this->fail('An overweight pickup offer must not be accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('remaining pickup weight capacity', $exception->getMessage());
        }
        $this->assertNull($shipment->fresh()->pickup_rider_id);
        $this->assertSame('offered', $offer->fresh()->status);

        $load->update(['estimated_weight_kg' => 12]);
        PickupAcceptanceService::accept($offer, $van);
        $this->assertEquals($van->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame('accepted', $offer->fresh()->status);
    }

    private function pickupFixture(): array
    {
        $buyer = User::factory()->create();
        $sellerOwner = User::factory()->create();
        $logisticsOwner = User::factory()->create(['status' => User::STATUS_ACTIVE]);

        $logistic = Logistic::create([
            'owner_user_id' => $logisticsOwner->id,
            'company_name' => 'Speedy Express',
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

        $pickupAddress = Address::create([
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
            'name' => 'Test Seller',
            'slug' => 'test-seller-retry',
            'status' => 'approved',
            'pickup_address_id' => $pickupAddress->id,
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-RETRY-001',
            'reference' => 'TEST-RETRY-' . uniqid(),
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
            'status' => 'ready_for_pickup',
            'subtotal_minor' => 10000,
            'shipping_minor' => 5000,
            'total_minor' => 15000,
        ]);

        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistic_id' => $logistic->id,
            'hub_id' => $hub->id,
            'origin_hub_id' => $hub->id,
            'routing_status' => 'awaiting_pickup',
            'tracking_code' => 'TEST-TRACKING-' . strtoupper(uniqid()),
            'tracking_number' => 'SPE-RETRY001',
            'status' => 'pending',
            'pickup_address' => 'San Pablo Sur',
            'delivery_address' => 'Bubukal',
        ]);

        $riderRole = Role::firstOrCreate(['name' => 'rider']);

        $rider = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'logistic_id' => $logistic->id,
            'logistic_status' => 'approved',
            'availability_status' => 'available',
            'max_capacity' => 10,
            'current_load' => 0,
            'assigned_zone' => 'Santa Cruz',
        ]);
        $rider->roles()->syncWithoutDetaching([$riderRole->id]);

        $logisticsOwner->roles()->attach(Role::firstOrCreate(['name' => 'logistics'])->id);

        return [$logistic, $hub, $shipment, $rider, $logisticsOwner];
    }
}
