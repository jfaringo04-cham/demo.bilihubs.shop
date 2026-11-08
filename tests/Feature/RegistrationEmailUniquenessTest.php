<?php

namespace Tests\Feature;

use App\Models\Logistic;
use App\Models\Role;
use App\Models\User;
use App\Mail\BuyerEmailOtp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\EmailVerificationOtp;
use Tests\SQLiteTestCase;

class RegistrationEmailUniquenessTest extends SQLiteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new \RuntimeException(
                'Registration tests require SQLite in-memory database.'
            );
        }

        // PostgreSQL REGEXP_REPLACE compatibility for SQLite tests only.
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

        $pdo->sqliteCreateFunction(
            'REGEXP_REPLACE',
            function ($value, $pattern, $replacement, $flags = '') {
                if ($value === null) {
                    return null;
                }

                $result = preg_replace(
                    '~' . str_replace('~', '\~', $pattern) . '~',
                    $replacement,
                    (string) $value,
                    $flags === 'g' ? -1 : 1
                );

                return $result;
            },
            -1
        );

        $this->migrateRegistrationSchema();

        foreach (['buyer', 'seller', 'logistics', 'rider', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        config(['services.googlemaps.key' => null]);
    }


    protected function tearDown(): void
    {
        if (
            config('database.default') === 'sqlite' &&
            config('database.connections.sqlite.database') === ':memory:'
        ) {
            Schema::disableForeignKeyConstraints();

            foreach (
                [
                    'seller_registration_details',
                    'email_verification_otps',
                    'notifications',
                    'role_user',
                    'roles',
                    'logistics',
                    'users',
                ] as $table
            ) {
                Schema::dropIfExists($table);
            }

            Schema::enableForeignKeyConstraints();
        }

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
            $table->string('pickup_postal_code', 4);
            $table->timestamps();
        });
        Schema::create('email_verification_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
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
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('api_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo')->nullable();
            $table->string('business_permit')->nullable();

            // Logistics registration details
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

            $table->text('service_areas')->nullable();

            $table->string('sec_certificate')->nullable();
            $table->string('bir_form_2303')->nullable();

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
            'terms' => '1',
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

        $response->assertRedirect(route('buyer.email.verify'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'newuser@example.com')->count());

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertTrue($user->isCustomer());
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertNull($user->email_verified_at);

        $this->assertDatabaseHas('email_verification_otps', [
            'user_id' => $user->id,
            'attempts' => 0,
        ]);

        Mail::assertSent(BuyerEmailOtp::class, 1);

        $response->assertSessionHas('buyer_otp_user_id', $user->id);
    }

    public function test_new_seller_email_creates_one_user_with_one_role(): void
    {
        Mail::fake();
        Storage::fake('supabase_private');

        $response = $this->post(route('register'), $this->registrationPayload([
            'role' => 'seller',
            'email' => 'newseller@example.com',
            'business_name' => 'Santos Store',
            'selling_categories' => [1],
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
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
        ]));

        $response->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('email', 'newseller@example.com')->count());

        $user = User::where('email', 'newseller@example.com')->firstOrFail();

        $this->assertTrue($user->isSeller());
        $this->assertFalse($user->isCustomer());
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertSame(1, $user->roles()->count());

        $this->assertDatabaseHas('seller_registration_details', [
            'user_id' => $user->id,
            'seller_type' => 'sole_proprietorship',
            'tin' => '123456789',
            'dti_registration_number' => 'DTI-2026-001',
            'pickup_province' => 'Laguna',
            'pickup_city' => 'Santa Cruz',
            'pickup_postal_code' => '4009',
        ]);

        $details = \App\Models\SellerRegistrationDetail::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertNotNull($details->dti_certificate);

        Storage::disk('supabase_private')->assertExists(
            $details->dti_certificate
        );
    }
    public function test_seller_registration_supports_all_business_types(): void
    {
        Mail::fake();
        Storage::fake('supabase_private');

        $types = [
            'individual' => [],
            'partnership' => [
                'seller_sec_registration_number' => 'SEC-2026-001',
                'seller_sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            ],
            'corporation' => [
                'seller_sec_registration_number' => 'SEC-2026-002',
                'seller_sec_certificate' => UploadedFile::fake()->create('sec-corp.pdf', 100, 'application/pdf'),
                'seller_bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
            ],
        ];

        foreach ($types as $type => $extraFields) {
            $email = $type . '@example.com';

            $mobileNumbers = [
                'individual' => '09171234561',
                'partnership' => '09171234562',
                'corporation' => '09171234563',
            ];

            $payload = array_merge(
                $this->registrationPayload([
                    'role' => 'seller',
                    'email' => $email,
                    'mobile_number' => $mobileNumbers[$type],
                    'business_name' => ucfirst($type) . ' Store',
                    'selling_categories' => [1],
                    'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
                    'seller_type' => $type,
                    'seller_tin' => '123456789',
                    'pickup_region' => 'CALABARZON',
                    'pickup_province' => 'Laguna',
                    'pickup_city' => 'Santa Cruz',
                    'pickup_barangay' => 'Poblacion',
                    'pickup_street' => 'Rizal Street',
                    'pickup_postal_code' => '4009',
                ]),
                $extraFields
            );

            $response = $this->post(route('register'), $payload);
            $response->assertSessionHasNoErrors();

            $user = User::where('email', $email)->firstOrFail();

            $this->assertSame(User::STATUS_PENDING, $user->status);

            $details = \App\Models\SellerRegistrationDetail::where(
                'user_id',
                $user->id
            )->firstOrFail();

            $this->assertSame($type, $details->seller_type);

            if ($type !== 'individual') {
                $this->assertNotNull($details->sec_certificate);
                Storage::disk('supabase_private')->assertExists(
                    $details->sec_certificate
                );
            }

            if ($type === 'corporation') {
                $this->assertNotNull($details->bir_form_2303);
                Storage::disk('supabase_private')->assertExists(
                    $details->bir_form_2303
                );
            }
        }
    }
    public function test_sole_proprietorship_requires_dti_certificate(): void
    {
        Mail::fake();
        Storage::fake('supabase_private');

        $email = 'seller-no-dti@example.com';

        $response = $this->post(route('register'), $this->registrationPayload([
            'role' => 'seller',
            'email' => $email,
            'business_name' => 'No DTI Store',
            'selling_categories' => [1],
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'seller_type' => 'sole_proprietorship',
            'seller_tin' => '123456789',
            'dti_registration_number' => 'DTI-2026-001',
            'pickup_region' => 'CALABARZON',
            'pickup_province' => 'Laguna',
            'pickup_city' => 'Santa Cruz',
            'pickup_barangay' => 'Poblacion',
            'pickup_street' => 'Rizal Street',
            'pickup_postal_code' => '4009',
        ]));

        $response->assertSessionHasErrors(['dti_certificate']);

        $this->assertDatabaseMissing('users', [
            'email' => $email,
        ]);
    }
    public function test_partnership_requires_sec_certificate(): void
    {
        Mail::fake();
        Storage::fake('supabase_private');

        $email = 'seller-no-sec@example.com';

        $response = $this->post(route('register'), $this->registrationPayload([
            'role' => 'seller',
            'email' => $email,
            'business_name' => 'No SEC Store',
            'selling_categories' => [1],
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'seller_type' => 'partnership',
            'seller_tin' => '123456789',
            'seller_sec_registration_number' => 'SEC-2026-001',
            'pickup_region' => 'CALABARZON',
            'pickup_province' => 'Laguna',
            'pickup_city' => 'Santa Cruz',
            'pickup_barangay' => 'Poblacion',
            'pickup_street' => 'Rizal Street',
            'pickup_postal_code' => '4009',
        ]));

        $response->assertSessionHasErrors(['seller_sec_certificate']);

        $this->assertDatabaseMissing('users', [
            'email' => $email,
        ]);
    }
    public function test_corporation_requires_bir_form_2303(): void
    {
        Mail::fake();
        Storage::fake('supabase_private');

        $email = 'seller-no-bir@example.com';

        $response = $this->post(route('register'), $this->registrationPayload([
            'role' => 'seller',
            'email' => $email,
            'business_name' => 'No BIR Corporation',
            'selling_categories' => [1],
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'seller_type' => 'corporation',
            'seller_tin' => '123456789',
            'seller_sec_registration_number' => 'SEC-2026-003',
            'seller_sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            'pickup_region' => 'CALABARZON',
            'pickup_province' => 'Laguna',
            'pickup_city' => 'Santa Cruz',
            'pickup_barangay' => 'Poblacion',
            'pickup_street' => 'Rizal Street',
            'pickup_postal_code' => '4009',
        ]));

        $response->assertSessionHasErrors(['seller_bir_form_2303']);

        $this->assertDatabaseMissing('users', [
            'email' => $email,
        ]);
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
            'sec_registration_number' => 'SEC-2026-LOG-001',
            'tin' => '123456789',
            'business_region' => 'NCR',
            'business_province' => 'Metro Manila',
            'business_city' => 'Quezon City',
            'business_barangay' => 'Commonwealth',
            'business_street' => 'Commonwealth Avenue',
            'business_postal_code' => '1121',
            'contact_first_name' => 'Owner',
            'contact_last_name' => 'Santos',
            'contact_position' => 'Operations Manager',
            'service_areas' => 'Quezon City, Metro Manila',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
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

        $this->post(route('register'), $this->registrationPayload())
            ->assertSessionHasNoErrors();

        // Simulate successful email OTP verification.
        $buyer = User::where('email', 'newuser@example.com')->firstOrFail();

        $buyer->forceFill([
            'email_verified_at' => now(),
        ])->save();

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
            ->assertRedirect(route('buyer.email.verify'))
            ->assertSessionHasNoErrors();

        // The double click / double submit replays the identical request.
        $response = $this->post(route('register'), $this->registrationPayload());

        $response->assertRedirect(route('buyer.email.verify'));
        $response->assertSessionHasNoErrors();

        // Exactly one buyer account should exist.
        $this->assertSame(1, User::where('email', 'newuser@example.com')->count());

        // Exactly one registration notification should be queued.
        Mail::assertQueued(
            \App\Mail\RegistrationNotification::class,
            1
        );
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
            'sec_registration_number' => 'SEC-2026-LOG-001',
            'tin' => '123456789',
            'business_region' => 'NCR',
            'business_province' => 'Metro Manila',
            'business_city' => 'Quezon City',
            'business_barangay' => 'Commonwealth',
            'business_street' => 'Commonwealth Avenue',
            'business_postal_code' => '1121',
            'contact_first_name' => 'Owner',
            'contact_last_name' => 'Santos',
            'contact_position' => 'Operations Manager',
            'service_areas' => 'Quezon City, Metro Manila',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $this->post(route('register'), $this->registrationPayload([
            'role' => 'logistic',
            'email' => 'newlogistics@example.com',
            'company_name' => 'New Logistics Co',
            'contact_person' => 'Owner Name',
            'company_email' => 'company@example.com',
            'company_phone' => '09181234567',
            'company_address' => 'Quezon City',
            'sec_registration_number' => 'SEC-2026-LOG-001',
            'tin' => '123456789',
            'business_region' => 'NCR',
            'business_province' => 'Metro Manila',
            'business_city' => 'Quezon City',
            'business_barangay' => 'Commonwealth',
            'business_street' => 'Commonwealth Avenue',
            'business_postal_code' => '1121',
            'contact_first_name' => 'Owner',
            'contact_last_name' => 'Santos',
            'contact_position' => 'Operations Manager',
            'service_areas' => 'Quezon City, Metro Manila',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'sec_certificate' => UploadedFile::fake()->create('sec.pdf', 100, 'application/pdf'),
            'bir_form_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
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
