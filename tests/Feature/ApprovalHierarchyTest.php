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

class ApprovalHierarchyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateApprovalSchema();

        foreach (['buyer', 'seller', 'logistics', 'rider', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        Route::middleware('customer')->get('/__probe/buyer', fn () => 'buyer-ok');
        Route::middleware('seller')->get('/__probe/seller', fn () => 'seller-ok');
        Route::middleware('rider')->get('/__probe/rider', fn () => 'rider-ok');
        Route::middleware('logistic_owner')->get('/__probe/logistic', fn () => 'logistic-ok');
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['notifications', 'orders', 'products', 'role_user', 'roles', 'logistics', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    /**
     * The project migrations contain MySQL/PostgreSQL-only raw constraint
     * statements, so the approval tables are created directly here to keep
     * this suite runnable on the in-memory SQLite test database.
     */
    private function migrateApprovalSchema(): void
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
            $table->string('province_name')->nullable();
            $table->string('municipality_name')->nullable();
            $table->string('barangay_name')->nullable();
            $table->string('house_number')->nullable();
            $table->string('street_address')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_permit')->nullable();
            $table->string('id_verification')->nullable();
            $table->string('vehicle_type')->nullable();
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

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('compliance_status')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
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

    private function makeUser(string $roleName, string $status = User::STATUS_ACTIVE, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['status' => $status], $attributes));
        $user->roles()->syncWithoutDetaching([Role::where('name', $roleName)->value('id')]);

        return $user->fresh();
    }

    private function makeLogistics(string $logisticStatus, string $userStatus = User::STATUS_ACTIVE): array
    {
        $owner = $this->makeUser('logistics', $userStatus);

        $logistic = Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Acme Logistics',
            'contact_person' => $owner->name,
            'email' => $owner->email,
            'phone' => '09171234567',
            'address' => 'Manila',
            'status' => $logisticStatus,
        ]);

        return [$owner->fresh(), $logistic];
    }

    private function makeRider(string $logisticStatus, ?Logistic $logistic = null, string $status = User::STATUS_PENDING, array $attributes = []): User
    {
        return $this->makeUser('rider', $status, array_merge([
            'logistic_id' => $logistic?->id,
            'logistic_status' => $logisticStatus,
        ], $attributes));
    }

    public function test_pending_registrations_exclude_riders(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('buyer', User::STATUS_PENDING);
        $this->makeUser('seller', User::STATUS_PENDING);
        [, $logistic] = $this->makeLogistics('pending', User::STATUS_PENDING);
        $this->makeRider('pending', $logistic);

        $response = $this->actingAs($admin)->get(route('admin.registrations.index'));

        $response->assertOk();
        $response->assertViewHas('applications', function ($applications) {
            $this->assertCount(3, $applications);
            $this->assertFalse($applications->contains(fn ($user) => $user->isRider()));

            return true;
        });
    }

    public function test_admin_approves_buyer(): void
    {
        $admin = $this->makeUser('admin');
        $buyer = $this->makeUser('buyer', User::STATUS_PENDING);

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $buyer))
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertSame(User::STATUS_ACTIVE, $buyer->fresh()->status);
        $this->assertNotNull($buyer->fresh()->approved_at);
    }

    public function test_admin_rejects_logistics_owner_and_syncs_company(): void
    {
        $admin = $this->makeUser('admin');
        [$owner, $logistic] = $this->makeLogistics('pending', User::STATUS_PENDING);

        $this->actingAs($admin)
            ->post(route('admin.registrations.reject', $owner), ['rejection_reason' => 'Incomplete permit'])
            ->assertRedirect(route('admin.registrations.index'));

        $owner->refresh();
        $logistic->refresh();

        $this->assertSame(User::STATUS_REJECTED, $owner->status);
        $this->assertSame('Incomplete permit', $owner->rejection_reason);
        $this->assertSame('rejected', $logistic->status);
        $this->assertNull($logistic->approved_at);
    }

    public function test_admin_cannot_approve_or_reject_riders(): void
    {
        $admin = $this->makeUser('admin');
        [, $logistic] = $this->makeLogistics('active');
        $rider = $this->makeRider('pending', $logistic);

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $rider))
            ->assertRedirect(route('admin.registrations.index'));

        $this->assertSame(User::STATUS_PENDING, $rider->fresh()->status);
        $this->assertSame('pending', $rider->fresh()->logistic_status);

        $this->actingAs($admin)
            ->get(route('admin.registrations.show', $rider))
            ->assertRedirect(route('admin.registrations.index'));
    }

    public function test_admin_rider_approval_routes_no_longer_exist(): void
    {
        $admin = $this->makeUser('admin');
        [, $logistic] = $this->makeLogistics('active');
        $rider = $this->makeRider('pending', $logistic);

        $this->actingAs($admin)
            ->post("/admin/logistics/riders/{$rider->id}/approve")
            ->assertNotFound();

        $this->assertSame('pending', $rider->fresh()->logistic_status);
    }

    public function test_pending_accounts_cannot_access_role_areas(): void
    {
        $this->actingAs($this->makeUser('buyer', User::STATUS_PENDING))->get('/__probe/buyer')->assertForbidden();
        $this->actingAs($this->makeUser('seller', User::STATUS_PENDING))->get('/__probe/seller')->assertForbidden();

        [, $logistic] = $this->makeLogistics('active');
        $this->actingAs($this->makeRider('pending', $logistic))->get('/__probe/rider')->assertForbidden();
    }

    public function test_active_accounts_can_access_role_areas(): void
    {
        $this->actingAs($this->makeUser('buyer'))->get('/__probe/buyer')->assertOk();
        $this->actingAs($this->makeUser('seller'))->get('/__probe/seller')->assertOk();
    }

    public function test_logistic_owner_access_requires_active_user_and_company(): void
    {
        [$pendingOwner] = $this->makeLogistics('pending', User::STATUS_PENDING);
        $this->actingAs($pendingOwner)->get('/__probe/logistic')->assertForbidden();

        [$inactiveCompanyOwner, $logistic] = $this->makeLogistics('pending');
        $logistic->update(['status' => 'rejected']);
        $this->actingAs($inactiveCompanyOwner)->get('/__probe/logistic')->assertForbidden();

        $logistic->update(['status' => 'active']);
        $this->actingAs($inactiveCompanyOwner->fresh())->get('/__probe/logistic')->assertOk();
    }

    public function test_rider_access_requires_logistic_approval(): void
    {
        [, $logistic] = $this->makeLogistics('active');

        $approvedRider = $this->makeRider('approved', $logistic, User::STATUS_ACTIVE);
        $this->actingAs($approvedRider)->get('/__probe/rider')->assertOk();

        $rejectedRider = $this->makeRider('rejected', $logistic, User::STATUS_ACTIVE);
        $this->actingAs($rejectedRider)->get('/__probe/rider')->assertForbidden();
    }

    public function test_logistics_company_approves_its_own_rider(): void
    {
        [$owner, $logistic] = $this->makeLogistics('active');
        $rider = $this->makeRider('pending', $logistic);

        $this->actingAs($owner)
            ->from('/logistic/riders')
            ->post(route('logistic.riders.approve', $rider))
            ->assertRedirect('/logistic/riders');

        $rider->refresh();

        $this->assertSame('approved', $rider->logistic_status);
        $this->assertSame(User::STATUS_ACTIVE, $rider->status);
        $this->assertNotNull($rider->logistic_approved_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $rider->id,
            'title' => 'Application Approved by Logistics',
        ]);
    }

    public function test_logistics_company_rejects_its_own_rider_with_reason(): void
    {
        [$owner, $logistic] = $this->makeLogistics('active');
        $rider = $this->makeRider('pending', $logistic);

        $this->actingAs($owner)
            ->post(route('logistic.riders.reject', $rider), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('pending', $rider->fresh()->logistic_status);

        $this->actingAs($owner)
            ->from('/logistic/riders')
            ->post(route('logistic.riders.reject', $rider), ['rejection_reason' => 'Invalid license'])
            ->assertRedirect('/logistic/riders');

        $rider->refresh();

        $this->assertSame('rejected', $rider->logistic_status);
        $this->assertSame('Invalid license', $rider->logistic_rejection_reason);
        $this->assertSame((int) $logistic->id, (int) $rider->logistic_id);
    }

    public function test_logistics_company_cannot_touch_riders_of_another_company(): void
    {
        [, $otherLogistic] = $this->makeLogistics('active');
        $foreignRider = $this->makeRider('pending', $otherLogistic);

        [$owner] = $this->makeLogistics('active');

        $this->actingAs($owner)->get(route('logistic.riders.show', $foreignRider))->assertForbidden();
        $this->actingAs($owner)->post(route('logistic.riders.approve', $foreignRider))->assertForbidden();
        $this->actingAs($owner)
            ->post(route('logistic.riders.reject', $foreignRider), ['rejection_reason' => 'No'])
            ->assertForbidden();

        $this->assertSame('pending', $foreignRider->fresh()->logistic_status);
    }

    public function test_pending_rider_cannot_log_in(): void
    {
        [, $logistic] = $this->makeLogistics('active');
        $rider = $this->makeRider('pending', $logistic, User::STATUS_PENDING, ['email' => 'rider@example.com']);

        $this->post(route('login'), [
            'email' => 'rider@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_approved_rider_can_log_in(): void
    {
        [, $logistic] = $this->makeLogistics('active');
        $rider = $this->makeRider('approved', $logistic, User::STATUS_ACTIVE, ['email' => 'rider2@example.com']);

        $this->post(route('login'), [
            'email' => 'rider2@example.com',
            'password' => 'password',
        ])->assertRedirect(route('rider.dashboard'));

        $this->assertAuthenticatedAs($rider);
    }

    public function test_rider_application_requires_an_active_logistics_company(): void
    {
        [, $pendingLogistic] = $this->makeLogistics('pending');

        $this->post(route('apply.rider.store'), $this->riderApplicationPayload([
            'email' => 'applicant@example.com',
            'logistic_id' => $pendingLogistic->id,
        ]))->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'applicant@example.com']);
    }

    public function test_rider_application_is_pending_until_the_company_approves(): void
    {
        [$owner, $logistic] = $this->makeLogistics('active');

        $this->post(route('apply.rider.store'), $this->riderApplicationPayload([
            'email' => 'applicant@example.com',
            'logistic_id' => $logistic->id,
        ]))->assertRedirect(route('login'));

        $applicant = User::where('email', 'applicant@example.com')->firstOrFail();

        $this->assertTrue($applicant->isRider());
        $this->assertSame(User::STATUS_PENDING, $applicant->status);
        $this->assertSame('pending', $applicant->logistic_status);
        $this->assertSame((int) $logistic->id, (int) $applicant->logistic_id);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'title' => 'New Rider Application',
        ]);

        $this->post(route('login'), [
            'email' => 'applicant@example.com',
            'password' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->actingAs($owner->fresh())
            ->from('/logistic/applications')
            ->post(route('logistic.riders.approve', $applicant))
            ->assertRedirect('/logistic/applications');

        $this->assertSame('approved', $applicant->fresh()->logistic_status);
        $this->assertSame(User::STATUS_ACTIVE, $applicant->fresh()->status);
    }

    public function test_admin_dashboard_counts_only_approved_accounts(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('buyer');
        $this->makeUser('buyer', User::STATUS_PENDING);
        $this->makeUser('seller');
        $this->makeUser('seller', User::STATUS_PENDING);

        [, $logistic] = $this->makeLogistics('active');
        $this->makeRider('approved', $logistic, User::STATUS_ACTIVE);
        $this->makeRider('pending', $logistic, User::STATUS_PENDING);
        $this->makeRider('approved', $logistic, User::STATUS_PENDING);
        [, $inactiveLogistic] = $this->makeLogistics('pending');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('bili-footer__bottom', false);
        $response->assertSee('bili-footer__logo', false);
        $response->assertViewHas('totalBuyers', 1);
        $response->assertViewHas('totalSellers', 1);
        $response->assertViewHas('totalRiders', 1);
        $response->assertViewHas('totalLogistics', 1);
        $response->assertViewHas('pendingRegistrationsCount', 2);

        $this->assertNotNull($inactiveLogistic);
    }

    public function test_buyer_and_logistics_registrations_wait_for_admin_approval(): void
    {
        Mail::fake();
        config(['services.googlemaps.key' => null]);

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'buyer',
            'email' => 'newbuyer@example.com',
        ]))->assertRedirect(route('login'));

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'logistic',
            'email' => 'newlogistics@example.com',
            'company_name' => 'New Logistics Co',
            'contact_person' => 'Owner Name',
            'company_email' => 'company@example.com',
            'company_phone' => '09181234567',
            'company_address' => 'Quezon City',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ]))->assertRedirect(route('login'));

        $buyer = User::where('email', 'newbuyer@example.com')->firstOrFail();
        $logisticsOwner = User::where('email', 'newlogistics@example.com')->firstOrFail();
        $logistic = Logistic::where('owner_user_id', $logisticsOwner->id)->firstOrFail();

        $this->assertTrue($buyer->isCustomer());
        $this->assertSame(User::STATUS_PENDING, $buyer->status);
        $this->assertTrue($logisticsOwner->isLogisticOwner());
        $this->assertSame(User::STATUS_PENDING, $logisticsOwner->status);
        $this->assertSame('pending', $logistic->status);

        $this->actingAs($logisticsOwner)->get('/__probe/logistic')->assertForbidden();

        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('admin.registrations.approve', $logisticsOwner));

        $this->assertSame(User::STATUS_ACTIVE, $logisticsOwner->fresh()->status);
        $this->assertSame('active', $logistic->fresh()->status);
        $this->actingAs($logisticsOwner->fresh())->get('/__probe/logistic')->assertOk();
    }

    private function registrationPayload(array $overrides = []): array
    {
        Storage::fake('public');

        return array_merge([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'sex' => 'Female',
            'email' => 'newuser@example.com',
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
        ], $overrides);
    }

    private function riderApplicationPayload(array $overrides = []): array
    {
        $payload = array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'email' => 'rider@example.com',
            'mobile_number' => '09171234567',
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
        ], $overrides);

        // The fake disk keeps the registration flow from touching real storage.
        Storage::fake('public');

        return $payload;
    }
}
