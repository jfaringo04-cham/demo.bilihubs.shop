<?php

namespace Tests\Feature;

use App\Models\EmailVerificationOtp;
use App\Models\Role;
use App\Models\User;
use App\Mail\BuyerEmailOtp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\SQLiteTestCase;

class BuyerEmailVerificationTest extends SQLiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            \Illuminate\Routing\Middleware\ThrottleRequests::class
        );

        // Safety: Never create or drop test tables outside SQLite memory.
        if (
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new \RuntimeException(
                'Buyer OTP tests require SQLite in-memory database.'
            );
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('email_verification_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        // Do not drop tables if database configuration is unsafe.
        if (
            config('database.default') === 'sqlite' &&
            config('database.connections.sqlite.database') === ':memory:'
        ) {
            Schema::disableForeignKeyConstraints();

            foreach (
                [
                    'email_verification_otps',
                    'role_user',
                    'roles',
                    'users',
                ] as $table
            ) {
                Schema::dropIfExists($table);
            }

            Schema::enableForeignKeyConstraints();
        }

        parent::tearDown();
    }

    public function test_correct_otp_verifies_email_but_keeps_buyer_pending(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );

        $buyer = User::create([
            'name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_PENDING,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        EmailVerificationOtp::create([
            'user_id' => $buyer->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
        ]);

        $response = $this
            ->withSession([
                'buyer_otp_user_id' => $buyer->id,
            ])
            ->post(route('buyer.email.verify.submit'), [
                'otp' => '123456',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();

        $buyer->refresh();

        $this->assertNotNull($buyer->email_verified_at);
        $this->assertSame(User::STATUS_PENDING, $buyer->status);
        $this->assertNull($buyer->approved_at);

        $this->assertDatabaseMissing('email_verification_otps', [
            'user_id' => $buyer->id,
        ]);
    }

    public function test_active_buyer_cannot_login_without_email_verification(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );

        $buyer = User::create([
            'name' => 'Unverified Buyer',
            'email' => 'unverified@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => null,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        $response = $this->post(route('login'), [
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('buyer.email.verify'));
        $response->assertSessionHas('buyer_otp_user_id');

        $this->assertGuest();

        $this->assertNull(
            $buyer->fresh()->email_verified_at
        );
    }

    public function test_invalid_otp_does_not_verify_buyer_email(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );

        $buyer = User::create([
            'name' => 'Invalid OTP Buyer',
            'email' => 'invalidotp@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_PENDING,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        $otpRecord = EmailVerificationOtp::create([
            'user_id' => $buyer->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
        ]);

        $response = $this
            ->withSession([
                'buyer_otp_user_id' => $buyer->id,
            ])
            ->post(route('buyer.email.verify.submit'), [
                'otp' => '999999',
            ]);

        $response->assertSessionHasErrors(['otp']);

        $this->assertNull(
            $buyer->fresh()->email_verified_at
        );

        $this->assertSame(
            User::STATUS_PENDING,
            $buyer->fresh()->status
        );

        $this->assertSame(
            1,
            $otpRecord->fresh()->attempts
        );
    }


    public function test_expired_otp_cannot_verify_buyer_email(): void
    {
        $buyer = User::create([
            'name' => 'Expired OTP Buyer',
            'email' => 'expiredotp@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_PENDING,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        $otpRecord = EmailVerificationOtp::create([
            'user_id' => $buyer->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
            'last_sent_at' => now()->subMinutes(6),
        ]);

        $response = $this
            ->withSession(['buyer_otp_user_id' => $buyer->id])
            ->post(route('buyer.email.verify.submit'), [
                'otp' => '123456',
            ]);

        $response->assertSessionHasErrors(['otp']);

        $this->assertNull($buyer->fresh()->email_verified_at);
        $this->assertSame(User::STATUS_PENDING, $buyer->fresh()->status);
        $this->assertSame(0, $otpRecord->fresh()->attempts);
    }


    public function test_buyer_cannot_verify_after_five_failed_otp_attempts(): void
    {
        $buyer = User::create([
            'name' => 'Locked OTP Buyer',
            'email' => 'lockedotp@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_PENDING,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        $otpRecord = EmailVerificationOtp::create([
            'user_id' => $buyer->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 4,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
        ]);

        // Fifth incorrect attempt.
        $response = $this
            ->withSession(['buyer_otp_user_id' => $buyer->id])
            ->post(route('buyer.email.verify.submit'), [
                'otp' => '999999',
            ]);

        $response->assertSessionHasErrors(['otp']);

        $this->assertSame(5, $otpRecord->fresh()->attempts);

        // Even the correct code must now be rejected.
        $response = $this
            ->withSession(['buyer_otp_user_id' => $buyer->id])
            ->post(route('buyer.email.verify.submit'), [
                'otp' => '123456',
            ]);

        $response->assertSessionHasErrors(['otp']);

        $this->assertNull($buyer->fresh()->email_verified_at);
        $this->assertSame(User::STATUS_PENDING, $buyer->fresh()->status);
        $this->assertSame(5, $otpRecord->fresh()->attempts);
    }


    public function test_buyer_cannot_resend_otp_within_sixty_seconds(): void
    {
        $buyer = User::create([
            'name' => 'Resend Cooldown Buyer',
            'email' => 'resendcooldown@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_PENDING,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        $otpRecord = EmailVerificationOtp::create([
            'user_id' => $buyer->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
        ]);

        $originalHash = $otpRecord->otp_hash;

        $response = $this
            ->withSession(['buyer_otp_user_id' => $buyer->id])
            ->post(route('buyer.email.otp.resend'));

        $response->assertSessionHasErrors(['otp']);

        $otpRecord->refresh();

        $this->assertSame($originalHash, $otpRecord->otp_hash);
        $this->assertSame(0, $otpRecord->attempts);
        $this->assertNull($buyer->fresh()->email_verified_at);
    }


    public function test_buyer_can_resend_otp_after_sixty_seconds(): void
    {
        Mail::fake();

        $buyer = User::create([
            'name' => 'Successful Resend Buyer',
            'email' => 'successfulresend@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_PENDING,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        $otpRecord = EmailVerificationOtp::create([
            'user_id' => $buyer->id,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 3,
            'expires_at' => now()->subMinute(),
            'last_sent_at' => now()->subSeconds(61),
        ]);

        $originalHash = $otpRecord->otp_hash;

        $response = $this
            ->withSession(['buyer_otp_user_id' => $buyer->id])
            ->post(route('buyer.email.otp.resend'));

        $response->assertSessionHasNoErrors();

        $otpRecord->refresh();

        $this->assertNotSame($originalHash, $otpRecord->otp_hash);
        $this->assertSame(0, $otpRecord->attempts);
        $this->assertTrue($otpRecord->expires_at->isFuture());
        $this->assertTrue($otpRecord->last_sent_at->greaterThan(now()->subSeconds(60)));

        Mail::assertSent(BuyerEmailOtp::class, 1);

        $this->assertNull($buyer->fresh()->email_verified_at);
        $this->assertSame(User::STATUS_PENDING, $buyer->fresh()->status);
    }

    public function test_failed_otp_email_removes_unsent_code(): void
    {
        $buyer = User::create([
            'name' => 'Failed Email Buyer',
            'email' => 'failedemail@example.com',
            'password' => Hash::make('Password123!'),
            'status' => User::STATUS_PENDING,
        ]);

        $buyerRole = Role::create(['name' => 'buyer']);
        $buyer->roles()->attach($buyerRole->id);

        Mail::shouldReceive('to')
            ->once()
            ->with($buyer->email)
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Simulated email failure'));

        try {
            app(\App\Http\Controllers\Auth\BuyerEmailVerificationController::class)
                ->sendOtp($buyer);

            $this->fail('Expected email sending to fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated email failure', $e->getMessage());
        }

        $this->assertDatabaseMissing('email_verification_otps', [
            'user_id' => $buyer->id,
        ]);

        $this->assertNull($buyer->fresh()->email_verified_at);
        $this->assertSame(User::STATUS_PENDING, $buyer->fresh()->status);
    }
}
