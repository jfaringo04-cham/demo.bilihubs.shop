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

    public function test_city_is_used_when_exact_barangay_has_no_hub(): void
    {
        $owner = User::factory()->create();
        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'City Fallback Express',
            'status' => 'active',
        ]);

        $cityHub = Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'Santa Cruz Hub',
            'address' => 'Santa Cruz',
            'barangay' => 'San Pablo Sur',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
        ]);

        $resolved = HubAssignmentService::resolveHubByLocality(
            barangay: 'No Hub Barangay',
            city: 'Santa Cruz',
            province: 'Laguna',
            logisticId: $logistic->id,
        );

        $this->assertSame($cityHub->id, $resolved['hub']?->id);
        $this->assertSame('city_fallback', $resolved['match']);
    }

    public function test_province_is_used_when_city_has_no_hub(): void
    {
        $owner = User::factory()->create();
        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Province Fallback Express',
            'status' => 'active',
        ]);

        $provinceHub = Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'Laguna Main Hub',
            'address' => 'Calamba',
            'barangay' => 'Real',
            'city' => 'Calamba',
            'province' => 'Laguna',
            'status' => 'active',
        ]);

        $resolved = HubAssignmentService::resolveHubByLocality(
            barangay: 'Unknown Barangay',
            city: 'Unknown City',
            province: 'Laguna',
            logisticId: $logistic->id,
        );

        $this->assertSame($provinceHub->id, $resolved['hub']?->id);
        $this->assertSame('province_fallback', $resolved['match']);
    }

    public function test_explicit_default_hub_is_used_after_locality_miss(): void
    {
        $owner = User::factory()->create();
        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Default Hub Express',
            'status' => 'active',
        ]);

        Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'Ordinary Hub',
            'address' => 'Laguna',
            'barangay' => 'Bubukal',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
            'is_default' => false,
        ]);

        $defaultHub = Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'Default Main Hub',
            'address' => 'Main',
            'barangay' => 'Main',
            'city' => 'Main City',
            'province' => 'Main Province',
            'status' => 'active',
            'is_default' => true,
        ]);

        $local = HubAssignmentService::resolveHubByLocality(
            barangay: 'Outside Coverage',
            city: 'Outside City',
            province: 'Outside Province',
            logisticId: $logistic->id,
        );

        $this->assertNull($local['hub']);
        $this->assertSame('unresolved', $local['match']);
        $this->assertSame($defaultHub->id, HubAssignmentService::fallbackHub($logistic->id)?->id);
    }

    public function test_no_silent_first_active_hub_fallback_when_default_is_missing(): void
    {
        $owner = User::factory()->create();
        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'No Default Express',
            'status' => 'active',
        ]);

        Hub::create([
            'logistic_id' => $logistic->id,
            'name' => 'Ordinary Hub',
            'address' => 'Laguna',
            'barangay' => 'Bubukal',
            'city' => 'Santa Cruz',
            'province' => 'Laguna',
            'status' => 'active',
            'is_default' => false,
        ]);

        $this->assertNull(HubAssignmentService::fallbackHub($logistic->id));
    }
}
