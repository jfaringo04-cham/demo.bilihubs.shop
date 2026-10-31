<?php

namespace Tests\Feature;

use App\Models\AdminWallet;
use App\Models\CodRemittance;
use App\Models\Logistic;
use App\Models\LogisticAdminRemittance;
use App\Models\LogisticCodWallet;
use App\Models\User;
use App\Services\CodCustodyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCodWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_rider_remittance_credits_logistics_cod_custody_once(): void
    {
        $logistic = Logistic::factory()->create();

        $remittance = CodRemittance::query()->create([
            'reference' => 'COD-TEST-1',
            'rider_user_id' => User::factory()->create()->id,
            'logistic_id' => $logistic->id,
            'total_minor' => 50000,
            'status' => 'confirmed',
            'submitted_at' => now(),
            'confirmed_at' => now(),
        ]);

        $service = app(CodCustodyService::class);

        $service->creditConfirmedRiderRemittance($remittance);
        $service->creditConfirmedRiderRemittance($remittance);

        $wallet = LogisticCodWallet::query()
            ->where('logistic_id', $logistic->id)
            ->firstOrFail();

        $this->assertSame(50000, $wallet->available_balance_minor);
        $this->assertDatabaseCount('logistic_cod_wallet_transactions', 1);
    }

    public function test_logistics_submission_reserves_cod_cash(): void
    {
        $logistic = Logistic::factory()->create();
        $user = User::factory()->create();

        LogisticCodWallet::query()->create([
            'logistic_id' => $logistic->id,
            'available_balance_minor' => 50000,
            'pending_balance_minor' => 0,
        ]);

        $service = app(CodCustodyService::class);

        $remittance = $service->submitToAdmin(
            $logistic->id,
            $user->id,
            30000,
            [
                'transfer_method' => 'bank_transfer',
                'transfer_reference' => 'BANK-123',
            ]
        );

        $wallet = LogisticCodWallet::query()
            ->where('logistic_id', $logistic->id)
            ->firstOrFail();

        $this->assertSame('pending', $remittance->status);
        $this->assertSame(20000, $wallet->available_balance_minor);
        $this->assertSame(30000, $wallet->pending_balance_minor);
    }

    public function test_admin_confirmation_moves_cod_cash_into_admin_wallet(): void
    {
        $logistic = Logistic::factory()->create();
        $logisticsUser = User::factory()->create();
        $admin = User::factory()->create();

        LogisticCodWallet::query()->create([
            'logistic_id' => $logistic->id,
            'available_balance_minor' => 50000,
            'pending_balance_minor' => 0,
        ]);

        $service = app(CodCustodyService::class);

        $remittance = $service->submitToAdmin(
            $logistic->id,
            $logisticsUser->id,
            30000,
            [
                'transfer_method' => 'cash',
            ]
        );

        $service->confirmAdminTransfer($remittance, $admin->id);

        $logisticWallet = LogisticCodWallet::query()
            ->where('logistic_id', $logistic->id)
            ->firstOrFail();

        $adminWallet = AdminWallet::query()->firstOrFail();

        $this->assertSame(20000, $logisticWallet->available_balance_minor);
        $this->assertSame(0, $logisticWallet->pending_balance_minor);
        $this->assertSame(30000, $adminWallet->available_balance_minor);

        $this->assertDatabaseHas('logistic_admin_remittances', [
            'id' => $remittance->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_admin_rejection_returns_pending_cash_to_logistics(): void
    {
        $logistic = Logistic::factory()->create();
        $logisticsUser = User::factory()->create();
        $admin = User::factory()->create();

        LogisticCodWallet::query()->create([
            'logistic_id' => $logistic->id,
            'available_balance_minor' => 50000,
            'pending_balance_minor' => 0,
        ]);

        $service = app(CodCustodyService::class);

        $remittance = $service->submitToAdmin(
            $logistic->id,
            $logisticsUser->id,
            30000,
            [
                'transfer_method' => 'cash',
            ]
        );

        $service->rejectAdminTransfer(
            $remittance,
            $admin->id,
            'Cash not received.'
        );

        $wallet = LogisticCodWallet::query()
            ->where('logistic_id', $logistic->id)
            ->firstOrFail();

        $this->assertSame(50000, $wallet->available_balance_minor);
        $this->assertSame(0, $wallet->pending_balance_minor);

        $this->assertDatabaseHas('logistic_admin_remittances', [
            'id' => $remittance->id,
            'status' => 'rejected',
        ]);
    }
}
