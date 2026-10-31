<?php

namespace Tests\Feature;

use App\Models\Logistic;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminApprovalVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateSchema();

        // PostgreSQL REGEXP_REPLACE compatibility for SQLite tests only.
        if (
            config('database.default') === 'sqlite' &&
            config('database.connections.sqlite.database') === ':memory:'
        ) {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

            $pdo->sqliteCreateFunction(
                'REGEXP_REPLACE',
                function ($value, $pattern, $replacement, $flags = '') {
                    if ($value === null) {
                        return null;
                    }

                    return preg_replace(
                        '~' . str_replace('~', '\~', $pattern) . '~',
                        $replacement,
                        (string) $value,
                        $flags === 'g' ? -1 : 1
                    );
                },
                -1
            );
        }

        foreach (['buyer', 'seller', 'logistics', 'rider', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        config(['services.googlemaps.key' => null]);

        Route::middleware('seller')->get('/__probe/seller', fn () => 'seller-ok');
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['seller_registration_details', 'email_verification_otps', 'notifications', 'products', 'orders', 'sellers', 'addresses', 'role_user', 'roles', 'logistics', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function migrateSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('sex')->nullable();
            $table->string('birthday')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('region')->nullable();
            $table->string('region_name')->nullable();
            $table->string('province')->nullable();
            $table->string('province_name')->nullable();
            $table->string('municipality')->nullable();
            $table->string('municipality_name')->nullable();
            $table->string('barangay')->nullable();
            $table->string('barangay_name')->nullable();
            $table->string('house_number')->nullable();
            $table->string('street_address')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_permit')->nullable();
            $table->string('id_verification')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->integer('max_capacity')->default(10);
            $table->integer('current_load')->default(0);
            $table->string('availability_status')->default('offline');
            $table->string('license_number')->nullable();
            $table->string('or_document')->nullable();
            $table->string('cr_document')->nullable();
            $table->json('selling_categories')->nullable();
            $table->string('status')->default(User::STATUS_ACTIVE);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('logistic_id')->nullable();
            $table->string('logistic_status')->nullable();
            $table->timestamp('logistic_approved_at')->nullable();
            $table->text('logistic_rejection_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('seller_registration_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('seller_type');
            $table->string('tin');
            $table->string('dti_registration_number')->nullable();
            $table->string('sec_registration_number')->nullable();
            $table->string('dti_certificate')->nullable();
            $table->string('sec_certificate')->nullable();
            $table->string('bir_form_2303')->nullable();
            $table->string('pickup_region');
            $table->string('pickup_province');
            $table->string('pickup_city');
            $table->string('pickup_barangay');
            $table->string('pickup_street');
            $table->string('pickup_postal_code');
            $table->timestamps();
        });

        Schema::create('email_verification_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();
            $table->string('otp_hash');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
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

        Schema::create('logistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id');
            $table->string('company_name');
            $table->string('sec_registration_number')->nullable();
            $table->string('tin')->nullable();
            $table->string('business_region')->nullable();
            $table->string('business_province')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_barangay')->nullable();
            $table->string('business_street')->nullable();
            $table->string('business_postal_code')->nullable();
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_position')->nullable();
            $table->json('service_areas')->nullable();
            $table->string('sec_certificate')->nullable();
            $table->string('bir_form_2303')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('api_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo')->nullable();
            $table->string('business_permit')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('label')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Philippines');
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('commission_bps')->default(0);
            $table->unsignedBigInteger('pickup_address_id')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->string('compliance_status')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
            $table->string('delivery_status')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('type')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->roles()->syncWithoutDetaching([Role::where('name', 'admin')->value('id')]);

        return $admin->fresh();
    }

    private function registrationPayload(array $overrides = []): array
    {
        Storage::fake('public');
        Storage::fake('supabase_private');

        return array_merge([
            'role' => 'seller',
            'terms' => '1',
            'first_name' => 'Test',
            'last_name' => 'Seller',
            'sex' => 'Female',
            'email' => 'new-seller-test@example.com',
            'mobile_number' => '09171234567',
            'birthday' => '1998-02-02',
            'age' => 28,
            'region' => 'NCR',
            'province' => 'Metro Manila',
            'municipality' => 'Makati',
            'barangay' => 'Bel-Air',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'id_verification' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            'business_name' => 'Test Store',
            'selling_categories' => [1],
            'seller_type' => 'sole_proprietorship',
            'seller_tin' => '123456789',
            'dti_registration_number' => 'DTI-2026-001',
            'dti_certificate' => UploadedFile::fake()->create('dti.pdf', 100, 'application/pdf'),
            'pickup_region' => 'CALABARZON',
            'pickup_province' => 'Laguna',
            'pickup_city' => 'Santa Cruz',
            'pickup_barangay' => 'Poblacion',
            'pickup_street' => 'Rizal Street',
            'pickup_postal_code' => '4009',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    private function registerSeller(string $email): User
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload(['email' => $email]))
            ->assertSessionHasNoErrors();

        $seller = User::where('email', $email)->firstOrFail();

        // Simulate successful email OTP verification.
        $seller->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return $seller->fresh();
    }

    private function sellerManagementEmails(): array
    {
        $emails = [];

        $this->actingAs($this->admin())
            ->get(route('admin.sellers'))
            ->assertOk()
            ->assertViewHas('sellers', function ($sellers) use (&$emails) {
                $emails = $sellers->pluck('email')->all();

                return true;
            });

        return $emails;
    }

    private function pendingRegistrationEmails(): array
    {
        $emails = [];

        $this->actingAs($this->admin())
            ->get(route('admin.registrations.index'))
            ->assertOk()
            ->assertViewHas('applications', function ($applications) use (&$emails) {
                $emails = $applications->pluck('email')->all();

                return true;
            });

        return $emails;
    }

    private function totalSellers(): int
    {
        $total = 0;

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalSellers', function ($value) use (&$total) {
                $total = $value;

                return true;
            });

        return $total;
    }

    /* ------------------------------------------------------------------
     | TEST 1 - new seller BEFORE admin approval
     | ------------------------------------------------------------------ */

    public function test_new_seller_is_pending_and_only_in_registrations(): void
    {
        $seller = $this->registerSeller('new-seller-test@example.com');

        $this->assertTrue($seller->isSeller());
        $this->assertSame(User::STATUS_PENDING, $seller->fresh()->status);

        $this->assertContains('new-seller-test@example.com', $this->pendingRegistrationEmails());
        $this->assertNotContains('new-seller-test@example.com', $this->sellerManagementEmails());
        $this->assertSame(0, $this->totalSellers());
    }

    /* ------------------------------------------------------------------
     | TEST 2 - AFTER admin approval
     | ------------------------------------------------------------------ */

    public function test_approved_seller_moves_to_seller_management_and_can_use_dashboard(): void
    {
        $seller = $this->registerSeller('new-seller-test@example.com');
        $admin = $this->admin();

        $this->assertSame(0, $this->totalSellers());

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $seller))
            ->assertRedirect(route('admin.registrations.index'));

        $seller->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $seller->status);
        $this->assertNotNull($seller->approved_at);

        $this->assertNotContains('new-seller-test@example.com', $this->pendingRegistrationEmails());
        $this->assertContains('new-seller-test@example.com', $this->sellerManagementEmails());
        $this->assertSame(1, $this->totalSellers());

        $this->actingAs($seller)->get('/__probe/seller')->assertOk();
    }

    /* ------------------------------------------------------------------
     | TEST 3 - rejected seller
     | ------------------------------------------------------------------ */

    public function test_rejected_seller_is_visible_nowhere_and_cannot_use_dashboard(): void
    {
        $seller = $this->registerSeller('rejected-seller@example.com');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.registrations.reject', $seller), ['rejection_reason' => 'Incomplete documents'])
            ->assertRedirect(route('admin.registrations.index'));

        $seller->refresh();
        $this->assertSame(User::STATUS_REJECTED, $seller->status);
        $this->assertSame('Incomplete documents', $seller->rejection_reason);

        $this->assertNotContains('rejected-seller@example.com', $this->pendingRegistrationEmails());
        $this->assertNotContains('rejected-seller@example.com', $this->sellerManagementEmails());
        $this->assertSame(0, $this->totalSellers());

        $this->actingAs($seller)->get('/__probe/seller')->assertForbidden();
    }

    /* ------------------------------------------------------------------
     | Existing pending sellers are hidden but never deleted
     | ------------------------------------------------------------------ */

    public function test_existing_pending_sellers_are_hidden_but_preserved(): void
    {
        $pendingSeller = User::factory()->create([
            'email' => 'existing-pending@example.com',
            'status' => User::STATUS_PENDING,
        ]);
        $pendingSeller->roles()->syncWithoutDetaching([Role::where('name', 'seller')->value('id')]);

        $approvedSeller = User::factory()->create([
            'email' => 'existing-approved@example.com',
            'status' => User::STATUS_ACTIVE,
        ]);
        $approvedSeller->roles()->syncWithoutDetaching([Role::where('name', 'seller')->value('id')]);

        $suspendedSeller = User::factory()->create([
            'email' => 'existing-suspended@example.com',
            'status' => User::STATUS_SUSPENDED,
        ]);
        $suspendedSeller->roles()->syncWithoutDetaching([Role::where('name', 'seller')->value('id')]);

        $this->assertNotContains('existing-pending@example.com', $this->sellerManagementEmails());
        $this->assertContains('existing-approved@example.com', $this->sellerManagementEmails());
        $this->assertContains('existing-suspended@example.com', $this->sellerManagementEmails());

        // Still in the database and still an open application.
        $this->assertNotNull(User::where('email', 'existing-pending@example.com')->first());
        $this->assertContains('existing-pending@example.com', $this->pendingRegistrationEmails());
    }

    /* ------------------------------------------------------------------
     | Same rule for buyers, logistics and riders
     | ------------------------------------------------------------------ */

    public function test_pending_buyer_is_only_in_registrations(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'buyer',
            'email' => 'pending-buyer@example.com',
            'terms' => '1',
        ]))->assertSessionHasNoErrors();

        $buyer = User::where('email', 'pending-buyer@example.com')->firstOrFail();

        // Before OTP verification, buyer is not yet in the admin queue.
        $this->assertNotContains('pending-buyer@example.com', $this->pendingRegistrationEmails());
        $this->assertNotContains('pending-buyer@example.com', $this->userManagementEmails());
        $this->assertSame(0, $this->totalBuyers());

        // Simulate successful email OTP verification.
        $buyer->forceFill([
            'email_verified_at' => now(),
        ])->save();

        // Verified buyer is now visible for admin review, but not yet approved.
        $this->assertContains('pending-buyer@example.com', $this->pendingRegistrationEmails());
        $this->assertNotContains('pending-buyer@example.com', $this->userManagementEmails());
        $this->assertSame(0, $this->totalBuyers());

        // Superadmin can approve only after email verification.
        $this->actingAs($this->admin())
            ->post(route('admin.registrations.approve', $buyer))
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertNotContains('pending-buyer@example.com', $this->pendingRegistrationEmails());
        $this->assertContains('pending-buyer@example.com', $this->userManagementEmails());
        $this->assertSame(1, $this->totalBuyers());
    }

    public function test_superadmin_cannot_approve_unverified_buyer(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'buyer',
            'email' => 'unverified-buyer@example.com',
            'terms' => '1',
        ]))->assertSessionHasNoErrors();

        $buyer = User::where(
            'email',
            'unverified-buyer@example.com'
        )->firstOrFail();

        $this->assertNull($buyer->email_verified_at);
        $this->assertSame(User::STATUS_PENDING, $buyer->status);

        // Attempt Superadmin approval without email verification.
        $this->actingAs($this->admin())
            ->post(route('admin.registrations.approve', $buyer))
            ->assertSessionHas('error');

        // Buyer must remain pending and unapproved.
        $buyer->refresh();

        $this->assertSame(User::STATUS_PENDING, $buyer->status);
        $this->assertNull($buyer->approved_at);

        $this->assertNotContains(
            'unverified-buyer@example.com',
            $this->pendingRegistrationEmails()
        );

        $this->assertSame(0, $this->totalBuyers());
    }

    public function test_pending_logistics_company_is_only_in_registrations(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'logistic',
            'email' => 'pending-logistics@example.com',
            'company_name' => 'Pending Logistics Co',
            'sec_registration_number' => 'SEC-2026-001',
            'tin' => '123456789',
            'business_region' => 'NCR',
            'business_province' => 'Metro Manila',
            'business_city' => 'Quezon City',
            'business_barangay' => 'Commonwealth',
            'business_street' => 'Commonwealth Avenue',
            'business_postal_code' => '1121',
            'contact_first_name' => 'Juan',
            'contact_last_name' => 'Dela Cruz',
            'contact_position' => 'Owner',
            'contact_person' => 'Owner Name',
            'service_areas' => 'Metro Manila',
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
            'company_email' => 'company@example.com',
            'company_phone' => '09181234567',
            'company_address' => 'Quezon City',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $owner = User::where('email', 'pending-logistics@example.com')->firstOrFail();
        $logistic = Logistic::where('owner_user_id', $owner->id)->firstOrFail();

        $this->assertSame('pending', $logistic->status);
        $this->assertContains('pending-logistics@example.com', $this->pendingRegistrationEmails());
        $this->assertNotContains($logistic->id, $this->logisticsManagementIds());

        $this->actingAs($this->admin())->post(route('admin.registrations.approve', $owner));

        $this->assertNotContains('pending-logistics@example.com', $this->pendingRegistrationEmails());
        $this->assertSame('active', $logistic->fresh()->status);
        $this->assertContains($logistic->id, $this->logisticsManagementIds());
    }

    public function test_rider_appears_in_admin_monitoring_only_after_logistics_approval(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $owner->roles()->syncWithoutDetaching([Role::where('name', 'logistics')->value('id')]);
        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Acme Logistics',
            'status' => 'active',
        ]);

        $this->post(route('apply.rider.store'), $this->riderPayload($logistic->id))->assertSessionHasNoErrors();

        $rider = User::where('email', 'rider@example.com')->firstOrFail();
        $this->assertSame('pending', $rider->logistic_status);

        $this->assertNotContains($rider->id, $this->adminRiderIds());
        $this->assertContains($rider->id, $owner->fresh()->ownedLogistic->riders()->pluck('users.id')->all());

        $this->actingAs($owner->fresh())
            ->from('/logistic/applications')
            ->post(route('logistic.riders.approve', $rider))
            ->assertRedirect('/logistic/applications');

        $this->assertSame('approved', $rider->fresh()->logistic_status);
        $this->assertContains($rider->id, $this->adminRiderIds());
    }

    private function riderPayload(int $logisticId): array
    {
        Storage::fake('public');

        return [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'email' => 'rider@example.com',
            'mobile_number' => '09181234567',
            'birthday' => '1995-05-05',
            'age' => 30,
            'region' => 'NCR',
            'province' => 'Metro Manila',
            'municipality' => 'Manila',
            'barangay' => 'Sampaloc',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'vehicle_type' => 'Motorcycle',
            'license_number' => 'N01-23-456789',
            'or_document' => UploadedFile::fake()->create('or.pdf', 100, 'application/pdf'),
            'cr_document' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
            'logistic_id' => $logisticId,
        ];
    }

    private function userManagementEmails(): array
    {
        $emails = [];

        $this->actingAs($this->admin())
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewHas('users', function ($users) use (&$emails) {
                $emails = $users->pluck('email')->all();

                return true;
            });

        return $emails;
    }

    private function logisticsManagementIds(): array
    {
        $ids = [];

        $this->actingAs($this->admin())
            ->get(route('admin.logistics.index'))
            ->assertOk()
            ->assertViewHas('logistics', function ($logistics) use (&$ids) {
                $ids = $logistics->pluck('id')->all();

                return true;
            });

        return $ids;
    }

    private function adminRiderIds(): array
    {
        $ids = [];

        $this->actingAs($this->admin())
            ->get(route('admin.riders'))
            ->assertOk()
            ->assertViewHas('riders', function ($riders) use (&$ids) {
                $ids = $riders->pluck('id')->all();

                return true;
            });

        return $ids;
    }

    private function totalBuyers(): int
    {
        $total = 0;

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalBuyers', function ($value) use (&$total) {
                $total = $value;

                return true;
            });

        return $total;
    }
}
