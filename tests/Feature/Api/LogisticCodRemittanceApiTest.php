<?php

namespace Tests\Feature\Api;

use App\Models\CodRemittance;
use App\Models\LogisticAdminRemittance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\SQLiteTestCase;

class LogisticCodRemittanceApiTest extends SQLiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! app()->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || DB::connection()->getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new \RuntimeException(
                'Mobile COD API tests require SQLite in-memory.'
            );
        }

        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('active');
            $table->string('logistic_status')->nullable();
            $table->foreignId('logistic_id')->nullable();
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

        Schema::create('logistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id');
            $table->string('company_name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistic_id');
            $table->foreignId('rider_id');
            $table->string('status')->default('delivered');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->nullable();
            $table->foreignId('collected_by')->nullable();
            $table->string('method');
            $table->string('status');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('rider_user_id');
            $table->foreignId('logistic_id');
            $table->unsignedBigInteger('total_minor');
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('confirmed_by')->nullable();
            $table->foreignId('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create(
            'cod_remittance_payments',
            function (Blueprint $table) {
                $table->id();
                $table->foreignId('cod_remittance_id');
                $table->foreignId('payment_id');
                $table->unsignedBigInteger('amount_minor');
                $table->timestamps();

                $table->unique(
                    ['cod_remittance_id', 'payment_id'],
                    'cod_remittance_payment_unique'
                );
            }
        );

        $migration = require database_path(
            'migrations/'
            . '2026_10_31_070000_'
            . 'create_cod_custody_wallets_and_admin_remittances.php'
        );

        $migration->up();

        DB::table('roles')->insert([
            [
                'id' => 1,
                'name' => 'rider',
            ],
            [
                'id' => 2,
                'name' => 'logistics',
            ],
        ]);
    }

    private function createUser(
        string $email,
        int $roleId,
        ?int $logisticId = null
    ): User {
        $id = DB::table('users')->insertGetId([
            'name' => 'API Test User',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
            'logistic_status' => 'approved',
            'logistic_id' => $logisticId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $id,
        ]);

        return User::findOrFail($id);
    }

    private function createActors(): array
    {
        $ownerA = $this->createUser(
            'logistics-a@example.com',
            2
        );

        $logisticA = DB::table('logistics')->insertGetId([
            'owner_user_id' => $ownerA->id,
            'company_name' => 'Logistics A',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownerB = $this->createUser(
            'logistics-b@example.com',
            2
        );

        $logisticB = DB::table('logistics')->insertGetId([
            'owner_user_id' => $ownerB->id,
            'company_name' => 'Logistics B',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rider = $this->createUser(
            'rider-api@example.com',
            1,
            $logisticA
        );

        return [
            'owner_a' => $ownerA,
            'owner_b' => $ownerB,
            'logistic_a' => $logisticA,
            'logistic_b' => $logisticB,
            'rider' => $rider,
        ];
    }

    private function createRemittance(
        int $riderId,
        int $logisticId,
        int $amountMinor = 100000
    ): CodRemittance {
        return CodRemittance::create([
            'reference' => 'TEST-COD-' . uniqid(),
            'rider_user_id' => $riderId,
            'logistic_id' => $logisticId,
            'total_minor' => $amountMinor,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    private function fundLogisticsWallet(
        int $logisticId,
        int $amountMinor
    ): void {
        DB::table('logistic_cod_wallets')->insert([
            'logistic_id' => $logisticId,
            'available_balance_minor' => $amountMinor,
            'pending_balance_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_cannot_access_logistics_cod_api(): void
    {
        $this->getJson('/api/logistic/cod-remittances')
            ->assertUnauthorized();

        $this->getJson('/api/logistic/cod-admin-remittances')
            ->assertUnauthorized();

        $this->postJson('/api/logistic/cod-admin-remittances', [
            'amount' => 100,
            'transfer_method' => 'cash',
        ])->assertUnauthorized();
    }

    public function test_rider_cannot_access_logistics_cod_api(): void
    {
        $actors = $this->createActors();

        $this->actingAs($actors['rider']);

        $this->getJson('/api/logistic/cod-remittances')
            ->assertForbidden();

        $this->getJson('/api/logistic/cod-admin-remittances')
            ->assertForbidden();
    }

    public function test_logistics_owner_sees_only_own_remittances(): void
    {
        $actors = $this->createActors();

        $remittanceA = $this->createRemittance(
            $actors['rider']->id,
            $actors['logistic_a'],
            100000
        );

        $remittanceB = $this->createRemittance(
            $actors['rider']->id,
            $actors['logistic_b'],
            200000
        );

        $this->actingAs($actors['owner_a']);

        $this->getJson('/api/logistic/cod-remittances')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.pending_count', 1)
            ->assertJsonCount(1, 'data.remittances')
            ->assertJsonPath(
                'data.remittances.0.id',
                $remittanceA->id
            );

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittanceB->id,
            'status' => 'pending',
        ]);
    }

    public function test_other_logistics_cannot_confirm_remittance(): void
    {
        $actors = $this->createActors();

        $remittance = $this->createRemittance(
            $actors['rider']->id,
            $actors['logistic_a']
        );

        $this->actingAs($actors['owner_b']);

        $this->postJson(
            "/api/logistic/cod-remittances/{$remittance->id}/confirm"
        )->assertForbidden();

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittance->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount(
            'logistic_cod_wallet_transactions',
            0
        );
    }

    public function test_confirming_remittance_credits_custody_once(): void
    {
        $actors = $this->createActors();

        $remittance = $this->createRemittance(
            $actors['rider']->id,
            $actors['logistic_a'],
            125000
        );

        $this->actingAs($actors['owner_a']);

        $url = "/api/logistic/cod-remittances/"
            . $remittance->id
            . '/confirm';

        $this->postJson($url)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittance->id,
            'status' => 'confirmed',
            'confirmed_by' => $actors['owner_a']->id,
        ]);

        $this->assertDatabaseHas('logistic_cod_wallets', [
            'logistic_id' => $actors['logistic_a'],
            'available_balance_minor' => 125000,
            'pending_balance_minor' => 0,
        ]);

        $this->assertDatabaseHas(
            'logistic_cod_wallet_transactions',
            [
                'cod_remittance_id' => $remittance->id,
                'type' => 'rider_cod_confirmed',
                'amount_minor' => 125000,
            ]
        );

        $this->postJson($url)
            ->assertUnprocessable();

        $this->assertSame(
            1,
            DB::table('logistic_cod_wallet_transactions')
                ->where('cod_remittance_id', $remittance->id)
                ->count()
        );

        $this->assertSame(
            125000,
            (int) DB::table('logistic_cod_wallets')
                ->where('logistic_id', $actors['logistic_a'])
                ->value('available_balance_minor')
        );
    }

    public function test_rejected_remittance_cannot_be_confirmed(): void
    {
        $actors = $this->createActors();

        $remittance = $this->createRemittance(
            $actors['rider']->id,
            $actors['logistic_a']
        );

        $this->actingAs($actors['owner_a']);

        $baseUrl = '/api/logistic/cod-remittances/'
            . $remittance->id;

        $this->postJson($baseUrl . '/reject', [
            'rejection_reason' => 'Cash not received.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittance->id,
            'status' => 'rejected',
            'rejection_reason' => 'Cash not received.',
        ]);

        $this->postJson($baseUrl . '/confirm')
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'logistic_cod_wallet_transactions',
            0
        );
    }

    public function test_admin_transfer_reserves_logistics_cod_balance(): void
    {
        $actors = $this->createActors();

        $this->fundLogisticsWallet(
            $actors['logistic_a'],
            150000
        );

        $this->actingAs($actors['owner_a']);

        $this->postJson('/api/logistic/cod-admin-remittances', [
            'amount' => 1000,
            'transfer_method' => 'cash',
            'transfer_reference' => 'CASH-TEST-001',
            'notes' => 'Cash handed over for review.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount_minor', 100000);

        $this->assertDatabaseHas('logistic_cod_wallets', [
            'logistic_id' => $actors['logistic_a'],
            'available_balance_minor' => 50000,
            'pending_balance_minor' => 100000,
        ]);

        $this->assertDatabaseHas('logistic_admin_remittances', [
            'logistic_id' => $actors['logistic_a'],
            'amount_minor' => 100000,
            'status' => 'pending',
        ]);

        $this->getJson('/api/logistic/cod-admin-remittances')
            ->assertOk()
            ->assertJsonPath(
                'data.wallet.available_balance_minor',
                50000
            )
            ->assertJsonPath(
                'data.wallet.pending_balance_minor',
                100000
            )
            ->assertJsonPath(
                'data.wallet.has_pending_transfer',
                true
            );
    }

    public function test_duplicate_pending_admin_transfer_is_rejected(): void
    {
        $actors = $this->createActors();

        $this->fundLogisticsWallet(
            $actors['logistic_a'],
            200000
        );

        $this->actingAs($actors['owner_a']);

        $url = '/api/logistic/cod-admin-remittances';

        $this->postJson($url, [
            'amount' => 1000,
            'transfer_method' => 'cash',
        ])->assertCreated();

        $this->postJson($url, [
            'amount' => 500,
            'transfer_method' => 'cash',
        ])->assertUnprocessable();

        $this->assertSame(
            1,
            LogisticAdminRemittance::query()
                ->where('logistic_id', $actors['logistic_a'])
                ->count()
        );

        $this->assertDatabaseHas('logistic_cod_wallets', [
            'logistic_id' => $actors['logistic_a'],
            'available_balance_minor' => 100000,
            'pending_balance_minor' => 100000,
        ]);
    }

    public function test_insufficient_cod_balance_blocks_admin_transfer(): void
    {
        $actors = $this->createActors();

        $this->fundLogisticsWallet(
            $actors['logistic_a'],
            5000
        );

        $this->actingAs($actors['owner_a']);

        $this->postJson('/api/logistic/cod-admin-remittances', [
            'amount' => 100,
            'transfer_method' => 'cash',
        ])->assertUnprocessable();

        $this->assertDatabaseCount(
            'logistic_admin_remittances',
            0
        );

        $this->assertDatabaseHas('logistic_cod_wallets', [
            'logistic_id' => $actors['logistic_a'],
            'available_balance_minor' => 5000,
            'pending_balance_minor' => 0,
        ]);
    }

    public function test_other_logistics_cannot_view_admin_transfers(): void
    {
        $actors = $this->createActors();

        $this->fundLogisticsWallet(
            $actors['logistic_a'],
            100000
        );

        $this->actingAs($actors['owner_a']);

        $this->postJson('/api/logistic/cod-admin-remittances', [
            'amount' => 500,
            'transfer_method' => 'cash',
        ])->assertCreated();

        $this->actingAs($actors['owner_b']);

        $this->getJson('/api/logistic/cod-admin-remittances')
            ->assertOk()
            ->assertJsonCount(0, 'data.remittances')
            ->assertJsonPath(
                'data.wallet.available_balance_minor',
                0
            )
            ->assertJsonPath(
                'data.wallet.pending_balance_minor',
                0
            );
    }
}
