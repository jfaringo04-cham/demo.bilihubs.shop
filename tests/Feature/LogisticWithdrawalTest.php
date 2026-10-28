<?php

namespace Tests\Feature;

use App\Models\LogisticWithdrawal;
use App\Services\LogisticWalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LogisticWithdrawalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'logistic_withdrawals',
            'logistic_wallet_transactions',
            'logistic_wallets',
            'logistics',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('logistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('company_name')->nullable();
            $table->timestamps();
        });

        Schema::create('logistic_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_id')->unique();
            $table->bigInteger('available_balance_minor')->default(0);
            $table->bigInteger('pending_balance_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('logistic_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_wallet_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('type', 50);
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('reference', 150)->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('logistic_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_wallet_id');
            $table->unsignedBigInteger('logistic_id');
            $table->bigInteger('amount_minor');
            $table->string('payment_method', 50);
            $table->string('account_name', 150);
            $table->string('account_number', 100);
            $table->string('bank_name', 150)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('transaction_reference', 200)->nullable();
            $table->text('logistic_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });

        config()->set('logistic_wallet.minimum_withdrawal_minor', 10000);
        config()->set('logistic_wallet.single_pending_withdrawal', true);
    }

    private function makeWallet(int $balanceMinor = 20000): array
    {
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Logistics Owner',
            'email' => uniqid('owner-') . '@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $logisticId = DB::table('logistics')->insertGetId([
            'owner_user_id' => $ownerId,
            'company_name' => 'Test Logistics',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $walletId = DB::table('logistic_wallets')->insertGetId([
            'logistic_id' => $logisticId,
            'available_balance_minor' => $balanceMinor,
            'pending_balance_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$logisticId, $walletId, $ownerId];
    }

    private function payoutData(): array
    {
        return [
            'payment_method' => 'gcash',
            'account_name' => 'Test Logistics',
            'account_number' => '09171234567',
            'bank_name' => null,
            'logistic_notes' => 'Weekly payout',
        ];
    }

    public function test_request_reserves_available_balance(): void
    {
        [$logisticId, $walletId] = $this->makeWallet();

        $withdrawal = app(LogisticWalletService::class)
            ->requestWithdrawal($logisticId, 10000, $this->payoutData());

        $this->assertSame('pending', $withdrawal->status);
        $this->assertDatabaseHas('logistic_wallets', [
            'id' => $walletId,
            'available_balance_minor' => 10000,
            'pending_balance_minor' => 10000,
        ]);
        $this->assertDatabaseHas('logistic_wallet_transactions', [
            'logistic_wallet_id' => $walletId,
            'type' => 'withdrawal_reserved',
            'amount_minor' => -10000,
        ]);
    }

    public function test_second_pending_withdrawal_is_blocked(): void
    {
        [$logisticId] = $this->makeWallet(30000);
        $service = app(LogisticWalletService::class);

        $service->requestWithdrawal($logisticId, 10000, $this->payoutData());

        $this->expectException(ValidationException::class);
        $service->requestWithdrawal($logisticId, 10000, $this->payoutData());
    }

    public function test_below_minimum_and_insufficient_balance_are_blocked(): void
    {
        [$logisticId] = $this->makeWallet(12000);
        $service = app(LogisticWalletService::class);

        try {
            $service->requestWithdrawal($logisticId, 5000, $this->payoutData());
            $this->fail('Below-minimum withdrawal should fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        try {
            $service->requestWithdrawal($logisticId, 15000, $this->payoutData());
            $this->fail('Insufficient-balance withdrawal should fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    public function test_admin_paid_clears_pending_without_returning_available(): void
    {
        [$logisticId, $walletId, $adminId] = $this->makeWallet();
        $service = app(LogisticWalletService::class);

        $withdrawal = $service->requestWithdrawal(
            $logisticId,
            10000,
            $this->payoutData()
        );

        $paid = $service->markWithdrawalPaid(
            $withdrawal,
            'GCASH-REF-001',
            'Verified payout',
            $adminId
        );

        $this->assertSame('paid', $paid->status);
        $this->assertDatabaseHas('logistic_wallets', [
            'id' => $walletId,
            'available_balance_minor' => 10000,
            'pending_balance_minor' => 0,
        ]);
        $this->assertDatabaseHas('logistic_withdrawals', [
            'id' => $withdrawal->id,
            'status' => 'paid',
            'transaction_reference' => 'GCASH-REF-001',
        ]);
    }

    public function test_reject_returns_reserved_funds(): void
    {
        [$logisticId, $walletId, $adminId] = $this->makeWallet();
        $service = app(LogisticWalletService::class);

        $withdrawal = $service->requestWithdrawal(
            $logisticId,
            10000,
            $this->payoutData()
        );

        $rejected = $service->rejectWithdrawal(
            $withdrawal,
            'Account number could not be verified.',
            $adminId
        );

        $this->assertSame('rejected', $rejected->status);
        $this->assertDatabaseHas('logistic_wallets', [
            'id' => $walletId,
            'available_balance_minor' => 20000,
            'pending_balance_minor' => 0,
        ]);
        $this->assertDatabaseHas('logistic_wallet_transactions', [
            'logistic_wallet_id' => $walletId,
            'type' => 'withdrawal_reversal',
            'amount_minor' => 10000,
        ]);
    }
}
