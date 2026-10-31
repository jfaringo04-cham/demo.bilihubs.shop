<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CodRemittanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! app()->environment('testing') ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:' ||
            DB::connection()->getDriverName() !== 'sqlite' ||
            DB::connection()->getDatabaseName() !== ':memory:'
        ) {
            throw new \RuntimeException(
                'COD remittance tests require SQLite in-memory.'
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

        Schema::create('cod_remittance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cod_remittance_id');
            $table->foreignId('payment_id');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->unique(
                ['cod_remittance_id', 'payment_id'],
                'cod_remittance_payment_unique'
            );
        });

        $codWalletMigration = require database_path(
            'migrations/2026_10_31_070000_create_cod_custody_wallets_and_admin_remittances.php'
        );

        $codWalletMigration->up();

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'rider'],
            ['id' => 2, 'name' => 'logistics'],
        ]);
    }

    private function user(
        string $email,
        int $roleId,
        ?int $logisticId = null
    ): User {
        $id = DB::table('users')->insertGetId([
            'name' => 'Test User',
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

    private function setupActors(): array
    {
        $owner = $this->user('owner@example.com', 2);

        $logisticId = DB::table('logistics')->insertGetId([
            'owner_user_id' => $owner->id,
            'company_name' => 'Speedy Express',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rider = $this->user(
            'rider@example.com',
            1,
            $logisticId
        );

        return [$rider, $owner, $logisticId];
    }

    private function payment(
        int $riderId,
        int $logisticId,
        int $amountMinor = 133601
    ): int {
        $shipmentId = DB::table('shipments')->insertGetId([
            'rider_id' => $riderId,
            'logistic_id' => $logisticId,
            'status' => 'delivered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('payments')->insertGetId([
            'shipment_id' => $shipmentId,
            'collected_by' => $riderId,
            'method' => 'cod',
            'status' => 'paid',
            'amount_minor' => $amountMinor,
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_rider_can_submit_cod_remittance(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated()
            ->assertJsonPath('remittance.status', 'pending');

        $this->assertDatabaseHas('cod_remittances', [
            'rider_user_id' => $rider->id,
            'logistic_id' => $logisticId,
            'total_minor' => 133601,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('cod_remittance_payments', [
            'payment_id' => $paymentId,
            'amount_minor' => 133601,
        ]);
    }

    public function test_duplicate_pending_remittance_is_rejected(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $this->postJson(route('rider.cod-remittances.store'), [
            'payment_ids' => [$paymentId],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_ids');

        $this->assertDatabaseCount('cod_remittances', 1);
        $this->assertDatabaseCount('cod_remittance_payments', 1);
    }

    public function test_other_rider_cannot_remit_payment(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $otherRider = $this->user(
            'other@example.com',
            1,
            $logisticId
        );

        $paymentId = $this->payment($rider->id, $logisticId);

        $this->actingAs($otherRider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('cod_remittances', 0);
    }

    public function test_logistics_owner_can_confirm_pending_remittance(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        $response = $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $remittanceId = $response->json('remittance.id');

        $this->actingAs($owner)
            ->postJson(route('logistic.cod-remittances.confirm', $remittanceId))
            ->assertOk();

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittanceId,
            'status' => 'confirmed',
            'confirmed_by' => $owner->id,
        ]);
    }

    public function test_other_logistics_owner_cannot_confirm(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        $response = $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $otherOwner = $this->user('otherowner@example.com', 2);

        DB::table('logistics')->insert([
            'owner_user_id' => $otherOwner->id,
            'company_name' => 'Other Logistics',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($otherOwner)
            ->postJson(route(
                'logistic.cod-remittances.confirm',
                $response->json('remittance.id')
            ))
            ->assertForbidden();

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $response->json('remittance.id'),
            'status' => 'pending',
        ]);
    }

    public function test_rejected_payment_can_be_resubmitted(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        $first = $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson(
                route(
                    'logistic.cod-remittances.reject',
                    $first->json('remittance.id')
                ),
                ['rejection_reason' => 'Test cash discrepancy']
            )
            ->assertOk();

        $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('cod_remittances', 2);
        $this->assertDatabaseCount('cod_remittance_payments', 2);

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $first->json('remittance.id'),
            'status' => 'rejected',
        ]);
    }

    public function test_confirmed_payment_cannot_be_resubmitted(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        $first = $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson(route(
                'logistic.cod-remittances.confirm',
                $first->json('remittance.id')
            ))
            ->assertOk();

        $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('cod_remittances', 1);
        $this->assertDatabaseCount('cod_remittance_payments', 1);
    }


    public function test_rider_sees_confirmed_remittance_in_history(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        // Rider submits collected COD cash.
        $submission = $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $remittanceId = $submission->json('remittance.id');

        // Logistics owner confirms the cash receipt.
        $this->actingAs($owner)
            ->postJson(route(
                'logistic.cod-remittances.confirm',
                $remittanceId
            ))
            ->assertOk();

        // Rider should see the confirmed remittance.
        $this->actingAs($rider)
            ->get(route('rider.cod-remittances.index'))
            ->assertOk()
            ->assertSee('Confirmed');

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittanceId,
            'rider_user_id' => $rider->id,
            'status' => 'confirmed',
            'confirmed_by' => $owner->id,
        ]);
    }

    public function test_rider_sees_rejected_remittance_and_can_resubmit(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $paymentId = $this->payment($rider->id, $logisticId);

        // Rider submits cash.
        $submission = $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $remittanceId = $submission->json('remittance.id');

        // Logistics rejects the remittance.
        $this->actingAs($owner)
            ->postJson(
                route('logistic.cod-remittances.reject', $remittanceId),
                ['rejection_reason' => 'Cash amount mismatch']
            )
            ->assertOk();

        // Rider should see the rejected status.
        $this->actingAs($rider)
            ->get(route('rider.cod-remittances.index'))
            ->assertOk()
            ->assertSee('Rejected');

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittanceId,
            'status' => 'rejected',
            'rejection_reason' => 'Cash amount mismatch',
        ]);

        // Rejected payment should become eligible for resubmission.
        $this->actingAs($rider)
            ->postJson(route('rider.cod-remittances.store'), [
                'payment_ids' => [$paymentId],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('cod_remittances', 2);
    }
}

