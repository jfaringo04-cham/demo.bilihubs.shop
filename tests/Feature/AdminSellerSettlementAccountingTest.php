<?php

namespace Tests\Feature;

use App\Models\AdminWallet;
use App\Models\AdminWalletTransaction;
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
            'shipping_address' => '123 Test Street, Test City',
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

        return SellerSettlement::query()
            ->findOrFail($id);
    }

    private function setAdminBalance(
        int $amountMinor
    ): AdminWallet {
        $wallet = app(
            CodCustodyService::class
        )->adminWallet();

        $wallet->update([
            'available_balance_minor' => $amountMinor,
        ]);

        return $wallet->fresh();
    }

    private function processSettlement(
        SellerSettlement $settlement
    ): void {
        $custodyService = app(
            CodCustodyService::class
        );

        $sellerWalletService = app(
            SellerWalletService::class
        );

        DB::transaction(function () use (
            $settlement,
            $custodyService,
            $sellerWalletService
        ) {
            $custodyService->debitSellerSettlement(
                $settlement
            );

            $sellerWalletService->creditApprovedSettlement(
                $settlement
            );
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

        $this->setAdminBalance(150000);

        $this->processSettlement(
            $settlement
        );

        $this->assertDatabaseHas(
            'admin_wallets',
            [
                'id' => 1,
                'available_balance_minor' => 60000,
            ]
        );

        $this->assertDatabaseHas(
            'seller_wallets',
            [
                'seller_id' => $sellerId,
                'available_balance_minor' => 90000,
                'pending_balance_minor' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'admin_wallet_transactions',
            [
                'seller_settlement_id' => $settlement->id,
                'type' => 'seller_settlement_debit',
                'amount_minor' => -90000,
                'balance_after_minor' => 60000,
            ]
        );

        $this->assertDatabaseHas(
            'seller_wallet_transactions',
            [
                'seller_settlement_id' => $settlement->id,
                'type' => 'settlement_credit',
                'amount_minor' => 90000,
                'balance_after_minor' => 90000,
            ]
        );

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

        $this->setAdminBalance(150000);

        $this->processSettlement(
            $settlement
        );

        $settlement->refresh();

        $this->processSettlement(
            $settlement
        );

        $this->assertSame(
            60000,
            (int) AdminWallet::query()
                ->findOrFail(1)
                ->available_balance_minor
        );

        $this->assertSame(
            90000,
            (int) SellerWallet::query()
                ->where(
                    'seller_id',
                    $sellerId
                )
                ->firstOrFail()
                ->available_balance_minor
        );

        $this->assertSame(
            1,
            AdminWalletTransaction::query()
                ->where(
                    'seller_settlement_id',
                    $settlement->id
                )
                ->where(
                    'type',
                    'seller_settlement_debit'
                )
                ->count()
        );

        $this->assertSame(
            1,
            SellerWalletTransaction::query()
                ->where(
                    'seller_settlement_id',
                    $settlement->id
                )
                ->where(
                    'type',
                    'settlement_credit'
                )
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

        $this->setAdminBalance(50000);

        try {
            $this->processSettlement(
                $settlement
            );

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
                ->findOrFail(1)
                ->available_balance_minor
        );

        $this->assertSame(
            0,
            AdminWalletTransaction::query()
                ->where(
                    'seller_settlement_id',
                    $settlement->id
                )
                ->where(
                    'type',
                    'seller_settlement_debit'
                )
                ->count()
        );

        $this->assertSame(
            0,
            SellerWalletTransaction::query()
                ->where(
                    'seller_settlement_id',
                    $settlement->id
                )
                ->where(
                    'type',
                    'settlement_credit'
                )
                ->count()
        );

        $sellerWallet = SellerWallet::query()
            ->where(
                'seller_id',
                $sellerId
            )
            ->first();

        if ($sellerWallet) {
            $this->assertSame(
                0,
                (int) $sellerWallet
                    ->available_balance_minor
            );
        }
    }
}
