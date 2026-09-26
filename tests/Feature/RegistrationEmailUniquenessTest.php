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

class RegistrationEmailUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateRegistrationSchema();

        foreach (['buyer', 'seller', 'logistics', 'rider', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        config(['services.googlemaps.key' => null]);
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (['notifications', 'role_user', 'roles', 'logistics', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function migrateRegistrationSchema(): void
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

    private function registrationPayload(array $overrides = []): array
    {
        Storage::fake('public');

        return array_merge([
            'role' => 'buyer',
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

    private function riderPayload(array $overrides = []): array
    {
        Storage::fake('public');

        return array_merge([
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
        ], $overrides);
    }

    private function activeLogistic(): Logistic
    {
        $owner = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $owner->roles()->syncWithoutDetaching([Role::where('name', 'logistics')->value('id')]);

        return Logistic::create([
            'owner_user_id' => $owner->id,
            'company_name' => 'Acme Logistics',
            'status' => 'active',
        ]);
    }

    /* ------------------------------------------------------------------
     | CASE 1 - a brand new address must be accepted exactly once
     | ------------------------------------------------------------------ */

    public function test_new_buyer_email_creates_exactly_one_pending_user(): void
    {
        Mail::fake();

        $response = $this->post(route('register'), $this->registrationPayload());

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'newuser@example.com')->count());

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertTrue($user->isCustomer());
        $this->assertSame(User::STATUS_PENDING, $user->status);
    }

    public function test_new_seller_email_creates_one_user_with_one_role(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'seller',
            'email' => 'newseller@example.com',
            'business_name' => 'Santos Store',
            'selling_categories' => [1],
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'newseller@example.com')->count());

        $user = User::where('email', 'newseller@example.com')->firstOrFail();
        $this->assertTrue($user->isSeller());
        $this->assertFalse($user->isCustomer());
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertSame(1, $user->roles()->count());
    }

    public function test_new_logistics_email_creates_one_user_and_one_company(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'logistic',
            'email' => 'newlogistics@example.com',
            'company_name' => 'New Logistics Co',
            'contact_person' => 'Owner Name',
            'company_email' => 'company@example.com',
            'company_phone' => '09181234567',
            'company_address' => 'Quezon City',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'newlogistics@example.com')->count());

        $user = User::where('email', 'newlogistics@example.com')->firstOrFail();
        $this->assertTrue($user->isLogisticOwner());
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertSame(1, Logistic::where('owner_user_id', $user->id)->count());
        $this->assertSame('pending', Logistic::where('owner_user_id', $user->id)->value('status'));
    }

    public function test_new_rider_email_keeps_selected_logistic_and_stays_pending(): void
    {
        Mail::fake();
        $logistic = $this->activeLogistic();

        $this->post(route('apply.rider.store'), $this->riderPayload(['logistic_id' => $logistic->id]))
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'rider@example.com')->count());

        $rider = User::where('email', 'rider@example.com')->firstOrFail();
        $this->assertTrue($rider->isRider());
        $this->assertSame((int) $logistic->id, (int) $rider->logistic_id);
        $this->assertSame('pending', $rider->logistic_status);
        $this->assertSame(User::STATUS_PENDING, $rider->status);
        $this->assertSame(1, $logistic->riders()->where('users.id', $rider->id)->count());
    }

    public function test_new_registrations_appear_in_admin_pending_queue(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->roles()->syncWithoutDetaching([Role::where('name', 'admin')->value('id')]);

        $this->post(route('register'), $this->registrationPayload());

        $this->actingAs($admin)
            ->get(route('admin.registrations.index'))
            ->assertOk()
            ->assertViewHas('applications', function ($applications) {
                $this->assertCount(1, $applications);
                $this->assertSame('newuser@example.com', $applications->first()->email);

                return true;
            });
    }

    /* ------------------------------------------------------------------
     | CASE 2 - an address that already existed must be rejected
     | ------------------------------------------------------------------ */

    public function test_existing_buyer_email_is_rejected_without_creating_anything(): void
    {
        Mail::fake();

        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $countBefore = User::count();

        $response = $this->post(route('register'), $this->registrationPayload(['email' => 'existing@example.com']));

        $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertSame($countBefore, User::count());
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
        $this->assertSame($existing->id, User::where('email', 'existing@example.com')->value('id'));
        $this->assertDatabaseCount('role_user', 0);
    }

    public function test_existing_email_is_case_and_whitespace_insensitive(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload(['email' => 'MixedCase@Example.com']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'mixedcase@example.com')->count());

        // Another applicant, a different browser session.
        $this->flushSession();

        $this->post(route('register'), $this->registrationPayload(['email' => '  MIXEDCASE@example.com  ']))
            ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertSame(1, User::where('email', 'mixedcase@example.com')->count());
    }

    public function test_existing_rider_email_is_rejected_without_creating_anything(): void
    {
        Mail::fake();
        $logistic = $this->activeLogistic();

        User::factory()->create(['email' => 'existing-rider@example.com']);
        $countBefore = User::count();

        $this->post(route('apply.rider.store'), $this->riderPayload([
            'email' => 'existing-rider@example.com',
            'logistic_id' => $logistic->id,
        ]))->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertSame($countBefore, User::count());
        $this->assertSame(0, $logistic->riders()->count());
    }

    /* ------------------------------------------------------------------
     | The reported bug: a second POST of the same request
     | ------------------------------------------------------------------ */

    public function test_repeat_submission_of_the_same_request_does_not_show_a_duplicate_error(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload())
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        // The double click / double submit replays the identical request.
        $response = $this->post(route('register'), $this->registrationPayload());

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('email', 'newuser@example.com')->count());
    }

    public function test_repeat_submission_does_not_duplicate_roles_or_company_records(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'logistic',
            'email' => 'newlogistics@example.com',
            'company_name' => 'New Logistics Co',
            'contact_person' => 'Owner Name',
            'company_email' => 'company@example.com',
            'company_phone' => '09181234567',
            'company_address' => 'Quezon City',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'logistic',
            'email' => 'newlogistics@example.com',
            'company_name' => 'New Logistics Co',
            'contact_person' => 'Owner Name',
            'company_email' => 'company@example.com',
            'company_phone' => '09181234567',
            'company_address' => 'Quezon City',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'newlogistics@example.com')->firstOrFail();

        $this->assertSame(1, User::where('email', 'newlogistics@example.com')->count());
        $this->assertSame(1, $user->roles()->count());
        $this->assertSame(1, Logistic::where('owner_user_id', $user->id)->count());
    }

    public function test_repeat_rider_submission_does_not_show_a_duplicate_error(): void
    {
        Mail::fake();
        $logistic = $this->activeLogistic();

        $this->post(route('apply.rider.store'), $this->riderPayload(['logistic_id' => $logistic->id]))
            ->assertSessionHasNoErrors();

        $this->post(route('apply.rider.store'), $this->riderPayload(['logistic_id' => $logistic->id]))
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'rider@example.com')->count());
        $this->assertSame(1, $logistic->riders()->count());
    }

    public function test_a_different_session_still_gets_the_duplicate_email_error(): void
    {
        Mail::fake();

        $this->post(route('register'), $this->registrationPayload())->assertSessionHasNoErrors();

        $this->flushSession();

        $this->post(route('register'), $this->registrationPayload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'newuser@example.com')->count());
    }

    /* ------------------------------------------------------------------
     | Unrelated failures must not masquerade as a duplicate e-mail
     | ------------------------------------------------------------------ */

    public function test_non_duplicate_database_errors_are_not_reported_as_duplicate_email(): void
    {
        Mail::fake();

        // A missing pivot role is a real configuration error, not a
        // duplicate e-mail: it must surface as a server error instead of
        // "The email has already been taken."
        Role::where('name', 'buyer')->delete();

        try {
            $this->withoutExceptionHandling()
                ->post(route('register'), $this->registrationPayload());

            $this->fail('Expected the missing role to surface as an error.');
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString('The email has already been taken.', $e->getMessage());
        }

        $this->assertSame(0, User::where('email', 'newuser@example.com')->count());
    }

    public function test_only_real_duplicate_key_violations_are_treated_as_duplicate_email(): void
    {
        $controller = new \App\Http\Controllers\Auth\RegisteredUserController();
        $method = new \ReflectionMethod($controller, 'isDuplicateEmailViolation');
        $method->setAccessible(true);

        $make = function (string $sqlState, string $message) {
            $previous = new \PDOException($message);
            $previous->errorInfo = [$sqlState, 0, $message];

            return new \Illuminate\Database\QueryException(
                'pgsql',
                'insert into "users" ("email") values (?)',
                [$message],
                $previous
            );
        };

        // PostgreSQL / MySQL / SQLite duplicate key violations on users.email
        $this->assertTrue($method->invoke($controller, $make('23505', 'duplicate key value violates unique constraint "users_email_unique"')));
        $this->assertTrue($method->invoke($controller, $make('23000', "Duplicate entry 'a@b.com' for key 'users.users_email_unique'")));
        $this->assertTrue($method->invoke($controller, $make('23000', 'UNIQUE constraint failed: users.email')));

        // Unrelated failures must not be converted into a duplicate e-mail error
        $this->assertFalse($method->invoke($controller, $make('23503', 'foreign key violation: role_user_role_id_fkey')));
        $this->assertFalse($method->invoke($controller, $make('42P01', 'relation "users" does not exist')));
        $this->assertFalse($method->invoke($controller, $make('23505', 'duplicate key value violates unique constraint "products_sku_unique"')));
        $this->assertFalse($method->invoke($controller, new \RuntimeException('boom')));
    }

    public function test_unique_email_rule_is_still_enforced_in_both_registration_flows(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Auth/RegisteredUserController.php'));

        $this->assertSame(2, substr_count($source, "'unique:users'"));
    }
}
