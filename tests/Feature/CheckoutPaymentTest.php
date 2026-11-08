<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\SQLiteTestCase;

class CheckoutPaymentTest extends SQLiteTestCase
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
            throw new \RuntimeException('CheckoutPaymentTest requires SQLite in-memory testing.');
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
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

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('product_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        $user = User::create([
            'name' => 'Payment Test Buyer',
            'email' => 'payment-buyer@example.test',
            'password' => bcrypt('password'),
            'status' => User::STATUS_ACTIVE,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        $this->actingAs($user->fresh());
    }

    public function test_gcash_is_rejected_by_server(): void
    {
        $this->post('/checkout', $this->payload('gcash'))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_credit_card_is_rejected_by_server(): void
    {
        $this->post('/checkout', $this->payload('credit_card'))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_cod_passes_payment_validation(): void
    {
        // Empty cart should redirect to cart, not fail payment validation.
        $this->post('/checkout', $this->payload('cod'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasNoErrors();
    }

    private function payload(string $paymentMethod): array
    {
        return [
            'shipping_address' => 'Santa Cruz, Laguna, Philippines',
            'payment_method' => $paymentMethod,
            'contact_name' => 'Payment Test Buyer',
            'contact_phone' => '09123456789',
        ];
    }
}
