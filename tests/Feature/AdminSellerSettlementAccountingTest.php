<?php

namespace Tests\Feature;

use App\Models\AdminWallet;
use App\Models\AdminWalletTransaction;
use App\Models\Logistic;
use App\Models\Role;
use App\Models\SellerSettlement;
use App\Models\SellerWallet;
use App\Models\SellerWalletTransaction;
use App\Models\User;
use App\Services\CodCustodyService;
use App\Services\SellerWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminSellerSettlementAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function createSeller(): int
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        return DB::table('sellers')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Test Seller',
            'slug' => 'test-seller-' . $user->id,
            'status' => 'approved',
            'commission_bps' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(): int
    {
        $buyer = User::factory()->create([
            'status' => 'active',
        ]);

        return DB::table('orders')->insertGetId([
            'user_id' => $buyer->id,
            'order_number' => 'TEST-' . uniqid(),
            'reference' => 'TEST-REF-' . uniqid(),
            'shipping_address' => json_encode([
                'address' => '123 Test Street, Test City',
            ]),
            'status' => 'completed',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal_minor' => 100000,
            'shipping_minor' => 0,
            'total_minor' => 100000,
            'confirmed_received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSellerOrder(
        int $sellerId,
        int $orderId
    ): int {
        return DB::table('seller_orders')->insertGetId([
            'order_id' => $orderId,
            'seller_id' => $sellerId,
            'status' => 'completed',
            'subtotal_minor' => 100000,
            'shipping_minor' => 0,
            'total_minor' => 100000,
            'return_status' => 'none',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createApprovedSettlement(
        int $sellerId,
        int $subtotalMinor = 100000,
        int $commissionMinor = 10000,
        int $netMinor = 90000
    ): SellerSettlement {
        $orderId = $this->createOrder();

        $sellerOrderId = $this->createSellerOrder(
            $sellerId,
            $orderId
        );

        $id = DB::table('seller_settlements')->insertGetId([
            'seller_order_id' => $sellerOrderId,
            'seller_id' => $sellerId,
            'subtotal_minor' => $subtotalMinor,
            'commission_minor' => $commissionMinor,
            'net_minor' => $netMinor,
            'status' => 'approved',
            'approved_by' => null,
            'approved_at' => now(),
            'paid_at' => null,
            'payout_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return SellerSettlement::query()->findOrFail($id);
    }

    /**
     * Creates a fully eligible COD settlement fixture
     * for testing the actual Admin approval controller.
     */
    private function createEligibleSettlement(): array
    {
        $sellerOwner = User::factory()->create([
            'status' => 'active',
        ]);

        $sellerId = DB::table('sellers')->insertGetId([
            'user_id' => $sellerOwner->id,
            'name' => 'HTTP Test Seller',
            'slug' => 'http-test-seller-' . $sellerOwner->id,
            'status' => 'approved',
            'commission_bps' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = $this->createOrder();

        $sellerOrderId = $this->createSellerOrder(
            $sellerId,
            $orderId
        );

        $logistic = Logistic::factory()->create();

        $rider = User::factory()->create([
            'status' => 'active',
        ]);

        $shipmentId = DB::table('shipments')->insertGetId([
            'logistic_id' => $logistic->id,
            'rider_id' => $rider->id,
            'seller_order_id' => $sellerOrderId,
            'tracking_number' => 'TEST-' . uniqid(),
            'tracking_code' => 'TEST-CODE-' . uniqid(),
            'status' => 'delivered',
            'delivered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Commission seller_id references the seller owner's user ID.
        DB::table('commissions')->insert([
            'order_id' => $orderId,
            'seller_id' => $sellerOwner->id,
            'order_total_minor' => 100000,
            'amount_minor' => 10000,
            'rate' => 10,
            'status' => 'pending',
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('payments')->insertGetId([
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'method' => 'cod',
            'status' => 'paid',
            'amount_minor' => 100000,
            'currency' => 'PHP',
            'paid_at' => now(),
            'collected_by' => $rider->id,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $remittanceId = DB::table('cod_remittances')->insertGetId([
            'reference' => 'TEST-COD-' . uniqid(),
            'rider_user_id' => $rider->id,
            'logistic_id' => $logistic->id,
            'total_minor' => 100000,
            'status' => 'confirmed',
            'submitted_at' => now(),
            'confirmed_at' => now(),
            'confirmed_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'notes' => 'HTTP approval test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cod_remittance_payments')->insert([
            'cod_remittance_id' => $remittanceId,
            'payment_id' => $paymentId,
            'amount_minor' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settlementId = DB::table('seller_settlements')->insertGetId([
            'seller_order_id' => $sellerOrderId,
            'seller_id' => $sellerId,
            'subtotal_minor' => 100000,
            'commission_minor' => 10000,
            'net_minor' => 90000,
            'status' => 'eligible',
            'approved_by' => null,
            'approved_at' => null,
            'paid_at' => null,
            'payout_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'seller_id' => $sellerId,
            'settlement' => SellerSettlement::query()->findOrFail(
                $settlementId
            ),
        ];
    }

    private function setAdminBalance(
        int $amountMinor
    ): AdminWallet {
        $wallet = app(CodCustodyService::class)->adminWallet();

        $wallet->update([
            'available_balance_minor' => $amountMinor,
        ]);

        return $wallet->fresh();
    }

    private function processSettlement(
        SellerSettlement $settlement
    ): void {
        $custodyService = app(CodCustodyService::class);

        $sellerWalletService = app(SellerWalletService::class);

        DB::transaction(function () use (
            $settlement,
            $custodyService,
            $sellerWalletService
        ) {
            $custodyService->debitSellerSettlement($settlement);

            $sellerWalletService->creditApprovedSettlement($settlement);
        });
    }

    public function test_seller_settlement_moves_money_from_admin_to_seller_wallet(): void
    {
        $sellerId = $this->createSeller();

        $settlement = $this->createApprovedSettlement(
            sellerId: $sellerId,
            subtotalMinor: 100000,
            commissionMinor: 10000,
            netMinor: 90000
        );

        $adminWallet = $this->setAdminBalance(150000);

        $this->processSettlement($settlement);

        $this->assertDatabaseHas('admin_wallets', [
            'id' => $adminWallet->id,
            'available_balance_minor' => 60000,
        ]);

        $this->assertDatabaseHas('seller_wallets', [
            'seller_id' => $sellerId,
            'available_balance_minor' => 90000,
            'pending_balance_minor' => 0,
        ]);

        $this->assertDatabaseHas('admin_wallet_transactions', [
            'seller_settlement_id' => $settlement->id,
            'type' => 'seller_settlement_debit',
            'amount_minor' => -90000,
            'balance_after_minor' => 60000,
        ]);

        $this->assertDatabaseHas('seller_wallet_transactions', [
            'seller_settlement_id' => $settlement->id,
            'type' => 'settlement_credit',
            'amount_minor' => 90000,
            'balance_after_minor' => 90000,
        ]);

        $this->assertSame(
            'wallet_credited',
            $settlement->fresh()->status
        );
    }

    public function test_repeated_processing_does_not_double_debit_or_credit(): void
    {
        $sellerId = $this->createSeller();

        $settlement = $this->createApprovedSettlement(
            sellerId: $sellerId,
            netMinor: 90000
        );

        $adminWallet = $this->setAdminBalance(150000);

        $this->processSettlement($settlement);

        $settlement->refresh();

        $this->processSettlement($settlement);

        $this->assertSame(
            60000,
            (int) AdminWallet::query()
                ->findOrFail($adminWallet->id)
                ->available_balance_minor
        );

        $this->assertSame(
            90000,
            (int) SellerWallet::query()
                ->where('seller_id', $sellerId)
                ->firstOrFail()
                ->available_balance_minor
        );

        $this->assertSame(
            1,
            AdminWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'seller_settlement_debit')
                ->count()
        );

        $this->assertSame(
            1,
            SellerWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'settlement_credit')
                ->count()
        );
    }

    public function test_insufficient_admin_cod_balance_blocks_seller_credit(): void
    {
        $sellerId = $this->createSeller();

        $settlement = $this->createApprovedSettlement(
            sellerId: $sellerId,
            netMinor: 90000
        );

        $adminWallet = $this->setAdminBalance(50000);

        try {
            $this->processSettlement($settlement);

            $this->fail(
                'Expected insufficient Admin COD balance validation error.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'settlement',
                $exception->errors()
            );
        }

        $this->assertSame(
            50000,
            (int) AdminWallet::query()
                ->findOrFail($adminWallet->id)
                ->available_balance_minor
        );

        $this->assertSame(
            0,
            AdminWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'seller_settlement_debit')
                ->count()
        );

        $this->assertSame(
            0,
            SellerWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'settlement_credit')
                ->count()
        );

        $sellerWallet = SellerWallet::query()
            ->where('seller_id', $sellerId)
            ->first();

        if ($sellerWallet) {
            $this->assertSame(
                0,
                (int) $sellerWallet->available_balance_minor
            );
        }
    }

    public function test_admin_debit_is_rolled_back_when_seller_wallet_credit_fails(): void
    {
        $sellerId = $this->createSeller();

        $settlement = $this->createApprovedSettlement(
            sellerId: $sellerId,
            netMinor: 90000
        );

        $adminWallet = $this->setAdminBalance(150000);

        // Simulate a failure during seller wallet credit.
        $this->mock(
            SellerWalletService::class,
            function ($mock) {
                $mock->shouldReceive('creditApprovedSettlement')
                    ->once()
                    ->andThrow(new \RuntimeException(
                        'Simulated seller wallet credit failure.'
                    ));
            }
        );

        try {
            $this->processSettlement($settlement);

            $this->fail(
                'Expected seller wallet credit failure.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated seller wallet credit failure.',
                $exception->getMessage()
            );
        }

        // Admin balance must be restored.
        $this->assertSame(
            150000,
            (int) AdminWallet::query()
                ->findOrFail($adminWallet->id)
                ->available_balance_minor
        );

        // The Admin debit ledger entry must also be rolled back.
        $this->assertSame(
            0,
            AdminWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'seller_settlement_debit')
                ->count()
        );

        // No seller credit transaction must exist.
        $this->assertSame(
            0,
            SellerWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'settlement_credit')
                ->count()
        );

        // Settlement must remain approved, not wallet_credited.
        $this->assertSame(
            'approved',
            $settlement->fresh()->status
        );
    }

    public function test_admin_http_approval_debits_admin_wallet_and_credits_seller_wallet(): void
    {
        $fixture = $this->createEligibleSettlement();

        $sellerId = $fixture['seller_id'];
        $settlement = $fixture['settlement'];

        $admin = User::factory()->create([
            'status' => 'active',
        ]);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
        ]);

        $admin->roles()->syncWithoutDetaching([
            $adminRole->id,
        ]);

        $adminWallet = $this->setAdminBalance(150000);

        $response = $this->actingAs($admin)
            ->from('/admin/seller-settlements')
            ->post(route(
                'admin.seller-settlements.approve',
                $settlement
            ));

        $response->assertRedirect('/admin/seller-settlements');

        $response->assertSessionHas('success');

        // Admin COD Wallet must be debited by ₱900.
        $this->assertSame(
            60000,
            (int) AdminWallet::query()
                ->findOrFail($adminWallet->id)
                ->available_balance_minor
        );

        // Seller Wallet must receive ₱900.
        $this->assertDatabaseHas('seller_wallets', [
            'seller_id' => $sellerId,
            'available_balance_minor' => 90000,
            'pending_balance_minor' => 0,
        ]);

        // Exactly one Admin debit.
        $this->assertSame(
            1,
            AdminWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'seller_settlement_debit')
                ->count()
        );

        // Exactly one Seller credit.
        $this->assertSame(
            1,
            SellerWalletTransaction::query()
                ->where('seller_settlement_id', $settlement->id)
                ->where('type', 'settlement_credit')
                ->count()
        );

        $this->assertDatabaseHas('admin_wallet_transactions', [
            'seller_settlement_id' => $settlement->id,
            'type' => 'seller_settlement_debit',
            'amount_minor' => -90000,
            'balance_after_minor' => 60000,
        ]);

        $this->assertDatabaseHas('seller_wallet_transactions', [
            'seller_settlement_id' => $settlement->id,
            'type' => 'settlement_credit',
            'amount_minor' => 90000,
            'balance_after_minor' => 90000,
        ]);

        // Verify final settlement status and approving admin.
        $this->assertDatabaseHas('seller_settlements', [
            'id' => $settlement->id,
            'status' => 'wallet_credited',
            'approved_by' => $admin->id,
        ]);
    }
}
