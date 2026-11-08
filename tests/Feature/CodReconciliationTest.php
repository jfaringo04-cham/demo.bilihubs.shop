<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\SQLiteTestCase;

class CodReconciliationTest extends SQLiteTestCase
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
                'COD reconciliation tests require SQLite in-memory.'
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

    private function remit(int $paymentId, int $riderId, int $logisticId, string $status): void
    {
        $id = DB::table('cod_remittances')->insertGetId([
            'reference' => 'TEST-' . uniqid(),
            'rider_user_id' => $riderId,
            'logistic_id' => $logisticId,
            'total_minor' => (int) DB::table('payments')->where('id', $paymentId)->value('amount_minor'),
            'status' => $status,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cod_remittance_payments')->insert([
            'cod_remittance_id' => $id,
            'payment_id' => $paymentId,
            'amount_minor' => (int) DB::table('payments')->where('id', $paymentId)->value('amount_minor'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dashboard(User $owner): array
    {
        $response = $this->actingAs($owner)
            ->get(route('logistic.cod-reconciliation.index'))
            ->assertOk()
            ->assertViewIs('logistic.cod-reconciliation');

        return [
            $response->viewData('totals'),
            $response->viewData('balances'),
        ];
    }

    public function test_unsubmitted_cash_is_counted(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();
        $this->payment($rider->id, $logisticId, 12345);

        [$totals, $balances] = $this->dashboard($owner);

        $this->assertEquals(12345, $totals['collected_minor']);
        $this->assertEquals(12345, $totals['unsubmitted_minor']);
        $this->assertEquals(0, $totals['pending_minor']);
        $this->assertEquals(0, $totals['confirmed_minor']);
        $this->assertCount(1, $balances);
    }

    public function test_pending_and_confirmed_are_separated(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();
        $pending = $this->payment($rider->id, $logisticId, 10000);
        $confirmed = $this->payment($rider->id, $logisticId, 25000);
        $this->remit($pending, $rider->id, $logisticId, 'pending');
        $this->remit($confirmed, $rider->id, $logisticId, 'confirmed');

        [$totals] = $this->dashboard($owner);

        $this->assertEquals(35000, $totals['collected_minor']);
        $this->assertEquals(10000, $totals['pending_minor']);
        $this->assertEquals(25000, $totals['confirmed_minor']);
        $this->assertEquals(0, $totals['unsubmitted_minor']);
    }

    public function test_rejected_then_resubmitted_payment_is_not_double_counted(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();
        $payment = $this->payment($rider->id, $logisticId, 133601);
        $this->remit($payment, $rider->id, $logisticId, 'rejected');

        [$before] = $this->dashboard($owner);
        $this->assertEquals(133601, $before['unsubmitted_minor']);

        $this->remit($payment, $rider->id, $logisticId, 'pending');
        [$after] = $this->dashboard($owner);

        $this->assertEquals(133601, $after['collected_minor']);
        $this->assertEquals(133601, $after['pending_minor']);
        $this->assertEquals(0, $after['unsubmitted_minor']);
    }

    public function test_balances_are_grouped_per_rider(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();
        $other = $this->user('second-rider@example.com', 1, $logisticId);
        $this->payment($rider->id, $logisticId, 5000);
        $pending = $this->payment($other->id, $logisticId, 7000);
        $this->remit($pending, $other->id, $logisticId, 'pending');

        [$totals, $balances] = $this->dashboard($owner);
        $byRider = $balances->keyBy('rider_id');

        $this->assertCount(2, $balances);
        $this->assertEquals(5000, $byRider[$rider->id]->unsubmitted_minor);
        $this->assertEquals(7000, $byRider[$other->id]->pending_minor);
        $this->assertEquals(12000, $totals['collected_minor']);
    }

    public function test_other_logistics_payments_are_excluded(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();
        $this->payment($rider->id, $logisticId, 10000);

        $otherOwner = $this->user('another-owner@example.com', 2);
        $otherLogisticId = DB::table('logistics')->insertGetId([
            'owner_user_id' => $otherOwner->id,
            'company_name' => 'Other Logistics',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherRider = $this->user('another-rider@example.com', 1, $otherLogisticId);
        $this->payment($otherRider->id, $otherLogisticId, 99000);

        [$first] = $this->dashboard($owner);
        [$second] = $this->dashboard($otherOwner);

        $this->assertEquals(10000, $first['collected_minor']);
        $this->assertEquals(99000, $second['collected_minor']);
    }

    public function test_empty_logistics_has_zero_balances(): void
    {
        [, $owner] = $this->setupActors();
        [$totals, $balances] = $this->dashboard($owner);

        $this->assertEmpty($balances->all());
        foreach ($totals as $value) {
            $this->assertEquals(0, $value);
        }
    }

    public function test_overdue_dashboard_counts_only_late_unsubmitted_cash(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $late = $this->payment($rider->id, $logisticId, 15000);
        $recent = $this->payment($rider->id, $logisticId, 5000);

        DB::table('payments')
            ->where('id', $late)
            ->update([
                'paid_at' => now()->subHours(25),
            ]);

        DB::table('payments')
            ->where('id', $recent)
            ->update([
                'paid_at' => now()->subHours(2),
            ]);

        $response = $this->actingAs($owner)
            ->get(route('logistic.cod-reconciliation.index'))
            ->assertOk();

        $stats = $response->viewData('overdueStats');

        $this->assertEquals(1, $stats['rider_count']);
        $this->assertEquals(1, $stats['payment_count']);
        $this->assertEquals(15000, $stats['total_minor']);
    }

    public function test_overdue_dashboard_excludes_pending_and_confirmed_but_counts_rejected(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $pending = $this->payment($rider->id, $logisticId, 10000);
        $confirmed = $this->payment($rider->id, $logisticId, 20000);
        $rejected = $this->payment($rider->id, $logisticId, 30000);

        foreach ([$pending, $confirmed, $rejected] as $id) {
            DB::table('payments')
                ->where('id', $id)
                ->update([
                    'paid_at' => now()->subHours(30),
                ]);
        }

        $this->remit($pending, $rider->id, $logisticId, 'pending');
        $this->remit($confirmed, $rider->id, $logisticId, 'confirmed');
        $this->remit($rejected, $rider->id, $logisticId, 'rejected');

        $response = $this->actingAs($owner)
            ->get(route('logistic.cod-reconciliation.index'))
            ->assertOk();

        $stats = $response->viewData('overdueStats');

        $this->assertEquals(1, $stats['rider_count']);
        $this->assertEquals(1, $stats['payment_count']);
        $this->assertEquals(30000, $stats['total_minor']);

        // Once resubmitted, the same cash must not be counted overdue.
        $this->remit($rejected, $rider->id, $logisticId, 'pending');

        $after = $this->actingAs($owner)
            ->get(route('logistic.cod-reconciliation.index'))
            ->assertOk()
            ->viewData('overdueStats');

        $this->assertEquals(0, $after['rider_count']);
        $this->assertEquals(0, $after['payment_count']);
        $this->assertEquals(0, $after['total_minor']);
    }

    public function test_overdue_dashboard_groups_riders_and_excludes_other_logistics(): void
    {
        [$rider, $owner, $logisticId] = $this->setupActors();

        $secondRider = $this->user(
            'deadline-second-rider@example.com',
            1,
            $logisticId
        );

        $first = $this->payment($rider->id, $logisticId, 10000);
        $second = $this->payment($secondRider->id, $logisticId, 25000);
        $third = $this->payment($secondRider->id, $logisticId, 5000);

        foreach ([$first, $second, $third] as $id) {
            DB::table('payments')
                ->where('id', $id)
                ->update([
                    'paid_at' => now()->subHours(26),
                ]);
        }

        $otherOwner = $this->user(
            'deadline-other-owner@example.com',
            2
        );

        $otherLogisticId = DB::table('logistics')->insertGetId([
            'owner_user_id' => $otherOwner->id,
            'company_name' => 'Another Delivery Company',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherRider = $this->user(
            'deadline-other-rider@example.com',
            1,
            $otherLogisticId
        );

        $foreignPayment = $this->payment(
            $otherRider->id,
            $otherLogisticId,
            99000
        );

        DB::table('payments')
            ->where('id', $foreignPayment)
            ->update([
                'paid_at' => now()->subHours(30),
            ]);

        $firstStats = $this->actingAs($owner)
            ->get(route('logistic.cod-reconciliation.index'))
            ->assertOk()
            ->viewData('overdueStats');

        $this->assertEquals(2, $firstStats['rider_count']);
        $this->assertEquals(3, $firstStats['payment_count']);
        $this->assertEquals(40000, $firstStats['total_minor']);

        $otherStats = $this->actingAs($otherOwner)
            ->get(route('logistic.cod-reconciliation.index'))
            ->assertOk()
            ->viewData('overdueStats');

        $this->assertEquals(1, $otherStats['rider_count']);
        $this->assertEquals(1, $otherStats['payment_count']);
        $this->assertEquals(99000, $otherStats['total_minor']);
    }
}
