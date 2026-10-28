<?php

namespace Tests\Feature;

use App\Models\Hub;
use App\Models\Logistic;
use App\Models\User;
use App\Services\HubAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubBarangayRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_barangay_match_is_preferred(): void
    {
        $owner = User::factory()->create();

        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Barangay Express',
            'status' => 'active',
        ]);

        $expected = Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'San Roque Hub',
            'address' => 'San Roque',
            'barangay' => 'San Roque',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
        ]);

        Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'Poblacion Hub',
            'address' => 'Poblacion',
            'barangay' => 'Poblacion',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
        ]);

        $resolved = HubAssignmentService::findHubByLocality(
            barangay: '  san roque ',
            city: 'SANTA CRUZ',
            province: 'laguna',
            logisticId: $logistic->id,
        );

        $this->assertNotNull($resolved);
        $this->assertSame($expected->id, $resolved->id);
    }

    public function test_destination_lookup_stays_inside_same_logistics_company(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $logisticA = Logistic::create([
            'owner_user_id' => $ownerA->id,
            'company_name' => 'Logistics A',
            'status' => 'active',
        ]);

        $logisticB = Logistic::create([
            'owner_user_id' => $ownerB->id,
            'company_name' => 'Logistics B',
            'status' => 'active',
        ]);

        $hubA = Hub::create([
            'logistic_id' => $logisticA->id,
            'name' => 'A - Bagumbayan',
            'address' => 'Bagumbayan',
            'barangay' => 'Bagumbayan',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
        ]);

        Hub::create([
            'logistic_id' => $logisticB->id,
            'name' => 'B - Bagumbayan',
            'address' => 'Bagumbayan',
            'barangay' => 'Bagumbayan',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
        ]);

        $resolved = HubAssignmentService::findHubByLocality(
            barangay: 'Bagumbayan',
            city: 'Santa Cruz',
            province: 'Laguna',
            logisticId: $logisticA->id,
        );

        $this->assertNotNull($resolved);
        $this->assertSame($hubA->id, $resolved->id);
        $this->assertSame($logisticA->id, $resolved->logistic_id);
    }
}
