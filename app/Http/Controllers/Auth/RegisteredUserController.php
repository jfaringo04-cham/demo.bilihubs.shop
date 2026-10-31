<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Auth\BuyerEmailVerificationController;

use App\Http\Controllers\Auth\SellerEmailVerificationController;

use App\Mail\NewRegistrationAdmin;

use App\Mail\RegistrationNotification;

use App\Models\Category;

use App\Models\Logistic;
use App\Models\PlatformSetting;

use App\Models\Role;

use App\Models\User;

use Illuminate\Auth\Events\Registered;

use Illuminate\Database\QueryException;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller

{

    /**

     * Normalize an e-mail address so that "Test@Email.com" and

     * "test@email.com " can never behave inconsistently between

     * validation, existence checks and storage.

     */

    private function normalizeEmail(?string $email): string

    {

        return Str::lower(trim((string) $email));
    }

    /**

     * Merge the normalized e-mail back into the request so the "unique"

     * rule and every later read of $request->email use the same value.

     */

    private function normalizeEmailInput(Request $request): void

    {

        if ($request->has('email')) {

            $request->merge(['email' => $this->normalizeEmail($request->input('email'))]);
        }
    }

    /**

     * A registration POST is only ever a duplicate of itself when the very

     * same browser session already registered that exact address a moment

     * ago (double click / double submit). Those must not surface as

     * "The email has already been taken." for the applicant who just

     * succeeded, so the success response is replayed instead.

     *

     * Any other existing address is left to the unique validation rule.

     */

    private function isRepeatSubmissionFromThisSession(Request $request, string $email, string $sessionKey): bool

    {

        return $email !== ''

            && $request->session()->get($sessionKey) === $email

            && User::where('email', $email)->exists();
    }

    /**

     * True only for a genuine duplicate-key violation on the users e-mail

     * column. Any other database failure is left to bubble up as a real error

     * instead of pretending the e-mail is a duplicate.

     */

    private function isDuplicateEmailViolation(\Throwable $e): bool

    {

        if (!$e instanceof QueryException) {

            return false;
        }

        $sqlState = (string) ($e->errorInfo[0] ?? '');

        // PostgreSQL unique_violation, MySQL/SQLite integrity constraint.

        $duplicateStates = ['23505', '23000', '19'];

        if (!in_array($sqlState, $duplicateStates, true)) {

            return false;
        }

        // Only the database error text and the constraint identifier count;

        // the SQL statement itself must not be used to guess the column.

        $databaseError = $e->getPrevious() instanceof \Throwable

            ? $e->getPrevious()->getMessage()

            : $e->getMessage();

        $identifier = (string) ($e->errorInfo[2] ?? '');

        $target = Str::lower($databaseError . ' ' . $identifier);

        $isUsersEmailConstraint = Str::contains($target, 'users_email_unique')

            || Str::contains($target, 'users.email')

            || (Str::contains($target, 'users') && Str::contains($target, 'email'));

        return $isUsersEmailConstraint;
    }

    private function backWithDuplicateEmail(Request $request)

    {

        return back()

            ->withInput($request->except(['password', 'password_confirmation', 'id_verification', 'business_permit']))

            ->withErrors(['email' => 'The email has already been taken.']);
    }

    public function create()

    {

        $categories = Category::whereNull('parent_id')

            ->orderBy('name')

            ->get();

        /*
         * Registration policy text comes directly from the same
         * Platform Settings records managed by the admin.
         *
         * This is display-only. The existing registration validation,
         * OTP flow, approval flow and form submission are unchanged.
         */
        $termsOfService = PlatformSetting::getValue(
            'terms_of_service',
            'Terms of Service are currently unavailable.'
        );

        $privacyPolicy = PlatformSetting::getValue(
            'privacy_policy',
            'Privacy Policy is currently unavailable.'
        );

        return view('auth.register', compact(
            'categories',
            'termsOfService',
            'privacyPolicy'
        ));
    }

    public function createRiderApplication()

    {

        $logistics = \App\Models\Logistic::where('status', 'active')->get();

        return view('auth.apply-rider', compact('logistics'));
    }

    public function storeRiderApplication(Request $request)

    {

        $this->normalizeEmailInput($request);

        $this->normalizeMobileNumberInput($request);

        $request->merge([
            'vehicle_type' => strtolower(
                trim((string) $request->input('vehicle_type'))
            ),
        ]);

        $email = $this->normalizeEmail($request->input('email'));

        if ($this->isRepeatSubmissionFromThisSession($request, $email, 'rider_registration_email')) {

            return redirect(route('login'))->with('success', 'Your rider application has already been submitted and is pending approval.');
        }

        $validated = $request->validate([

            'first_name' => [

                'required',

                'string',

                'min:2',

                'max:50',

                "regex:/^[\p{L}]+(?:[ '\x{2019}-][\p{L}]+)*$/u",

            ],

            'middle_name' => [

                'nullable',

                'string',

                'max:1',

                'regex:/^\p{L}$/u',

            ],

            'last_name' => [

                'required',

                'string',

                'min:2',

                'max:50',

                "regex:/^[\p{L}]+(?:[ '\x{2019}-][\p{L}]+)*$/u",

            ],

            'sex' => ['required', 'in:Male,Female'],

            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],

            'mobile_number' => [

                'required',

                'string',

                'regex:/^09[0-9]{9}$/',

                'unique:users,mobile_number',

            ],

            'birthday' => ['required', 'date'],

            'age' => ['required', 'integer', 'min:0', 'max:120'],

            'region' => ['required', 'string', 'max:255'],

            'province' => ['required', 'string', 'max:255'],

            'municipality' => ['required', 'string', 'max:255'],

            'barangay' => ['required', 'string', 'max:255'],

            'house_number' => ['nullable', 'string', 'max:50'],

            'street_address' => ['nullable', 'string', 'max:255'],

            'password' => [

                'required',

                'confirmed',

                Rules\Password::min(8)

                    ->mixedCase()

                    ->numbers()

                    ->symbols(),

            ],

            'vehicle_type' => ['required', 'string', 'in:motorcycle,car,van,truck'],

            'license_number' => ['required', 'string', 'max:255'],

            'or_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'cr_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'logistic_id' => ['required', 'exists:logistics,id'],

        ]);

        $logistic = \App\Models\Logistic::where('id', $validated['logistic_id'])

            ->where('status', 'active')

            ->firstOrFail();

        $middleName = $validated['middle_name'] ?? null;

        $houseNumber = $validated['house_number'] ?? '';

        $streetAddress = $validated['street_address'] ?? '';

        $municipality = $validated['municipality'] ?? '';

        $userData = [

            'first_name' => $validated['first_name'],

            'middle_name' => $middleName,

            'last_name' => $validated['last_name'],

            'name' => $validated['last_name'] . ', ' . $validated['first_name'] . ' ' . $middleName,

            'sex' => $validated['sex'],

            'email' => $validated['email'],

            'mobile_number' => $validated['mobile_number'],

            'birthday' => $validated['birthday'],

            'age' => $validated['age'],

            'region' => $validated['region'],

            'province' => $validated['province'],

            'municipality' => $municipality,

            'barangay' => $validated['barangay'],

            'house_number' => $houseNumber,

            'street_address' => $streetAddress,

            'password' => Hash::make($validated['password']),

            'phone' => $validated['mobile_number'],

            'status' => User::STATUS_PENDING,

            'vehicle_type' => $validated['vehicle_type'],

            'license_number' => $validated['license_number'],

            'logistic_id' => $logistic->id,

            'logistic_status' => 'pending',

        ];

        $uploadedPaths = [];

        try {

            foreach (

                [

                    'id_verification' => 'customer-documents',

                    'or_document' => 'rider-documents',

                    'cr_document' => 'rider-documents',

                ] as $field => $folder

            ) {

                if (!$request->hasFile($field)) {

                    continue;
                }

                $path = $request->file($field)

                    ->store($folder, 'supabase_private');

                if (!$path) {

                    throw new \RuntimeException(

                        "Private upload failed for {$field}."

                    );
                }

                $uploadedPaths[] = $path;

                $userData[$field] = $path;
            }

            $user = DB::transaction(function () use ($userData) {

                $user = User::create($userData);

                $riderRole = Role::where('name', 'rider')->firstOrFail();

                $user->roles()->syncWithoutDetaching([

                    $riderRole->id,

                ]);

                return $user;
            });
        } catch (\Throwable $e) {

            // Delete only files uploaded by this registration attempt.

            foreach ($uploadedPaths as $path) {

                try {

                    Storage::disk('supabase_private')->delete($path);
                } catch (\Throwable $cleanupException) {

                    report($cleanupException);
                }
            }

            if (

                $e instanceof QueryException

                && $this->isDuplicateEmailViolation($e)

            ) {

                return $this->backWithDuplicateEmail($request);
            }

            throw $e;
        }

        $logistic = \App\Models\Logistic::find($validated['logistic_id']);

        if ($logistic) {

            $logisticOwner = \App\Models\User::find($logistic->owner_user_id);

            if ($logisticOwner) {

                \App\Models\Notification::create([

                    'user_id' => $logisticOwner->id,

                    'title' => 'New Rider Application',

                    'message' => $user->name . ' has applied to join ' . $logistic->company_name . ' as a rider. Please review and approve.',

                    'type' => 'logistic',

                    'link' => route('logistic.applications'),

                ]);
            }
        }

        $request->session()->put('rider_registration_email', $user->email);

        return redirect(route('login'))->with('success', 'Your rider application has been submitted to ' . $logistic->company_name . '. Please wait for approval.');
    }

    private function geocodeAddress($address)

    {

        $apiKey = config('services.googlemaps.key');

        if (!$apiKey || !$address) {

            return null;
        }

        $url = 'https\://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;

        $response = @file_get_contents($url);

        if (!$response) {

            return null;
        }

        $data = json_decode($response, true);

        if ($data['status'] === 'OK' && !empty($data['results'])) {

            $location = $data['results'][0]['geometry']['location'];

            return [

                'lat' => $location['lat'],

                'lng' => $location['lng'],

            ];
        }

        return null;
    }

    public function store(Request $request)

    {

        $this->normalizeEmailInput($request);

        $this->normalizeMobileNumberInput($request);

        $request->merge([
            'vehicle_type' => strtolower(
                trim((string) $request->input('vehicle_type'))
            ),
        ]);

        $email = $this->normalizeEmail($request->input('email'));

        if ($this->isRepeatSubmissionFromThisSession($request, $email, 'registration_email')) {

            $existingUser = User::where('email', $email)->first();

            if (

                $existingUser &&

                $existingUser->isPending() &&

                $existingUser->roles()->where('name', 'buyer')->exists() &&

                $existingUser->email_verified_at === null

            ) {

                $request->session()->put('buyer_otp_user_id', $existingUser->id);

                return redirect()->route('buyer.email.verify')

                    ->with('success', 'Please verify your email address using the OTP sent to you.');
            }

            if (

                $existingUser &&

                $existingUser->isPending() &&

                $existingUser->roles()->where('name', 'seller')->exists() &&

                $existingUser->email_verified_at === null

            ) {

                $request->session()->put('seller_otp_user_id', $existingUser->id);

                return redirect()->route('seller.email.verify')

                    ->with('success', 'Please complete your Seller email verification.');
            }

            return redirect()->route('login')

                ->with('success', 'Your registration has already been submitted. Please check your account status.');
        }

        $validated = $request->validate([

            'terms' => ['required', 'accepted'],

            'first_name' => [

                'required',

                'string',

                'min:2',

                'max:50',

                "regex:/^[\p{L}]+(?:[ '\x{2019}-][\p{L}]+)*$/u",

            ],

            'middle_name' => [

                'nullable',

                'string',

                'max:1',

                'regex:/^\p{L}$/u',

            ],

            'last_name' => [

                'required',

                'string',

                'min:2',

                'max:50',

                "regex:/^[\p{L}]+(?:[ '\x{2019}-][\p{L}]+)*$/u",

            ],

            'sex' => ['required', 'in:Male,Female'],

            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],

            'mobile_number' => [

                'required',

                'string',

                'regex:/^09[0-9]{9}$/',

                'unique:users,mobile_number',

            ],

            'birthday' => [

                'required',

                'date',

                'before_or_equal:' . now()->subYears(18)->toDateString(),

            ],

            'age' => [

                'required',

                'integer',

                'min:18',

                'max:120',

            ],

            'role' => ['required', 'in:buyer,seller,logistic'],

            'region' => ['required', 'string', 'max:255'],

            'province' => ['required', 'string', 'max:255'],

            'municipality' => ['nullable', 'string', 'max:255'],

            'barangay' => ['required', 'string', 'max:255'],

            'house_number' => ['nullable', 'string', 'max:50'],

            'street_address' => ['nullable', 'string', 'max:255'],

            'password' => [

                'required',

                'confirmed',

                Rules\Password::min(8)

                    ->mixedCase()

                    ->numbers()

                    ->symbols(),

            ],

            'id_verification' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'business_name' => ['nullable', 'string', 'max:255'],

            'contact_person' => ['nullable', 'string', 'max:255'],

            'selling_categories' => ['nullable', 'array'],

            'business_permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'company_name' => ['nullable', 'string', 'max:255'],

            'company_email' => ['nullable', 'string', 'email', 'max:255'],

            'company_phone' => ['nullable', 'string', 'max:500'],

            'company_address' => ['nullable', 'string', 'max:500'],

            'api_address' => ['nullable', 'string', 'max:500'],

        ]);

        $role = match ($request->role) {

            'buyer' => 'customer',

            'logistic' => 'logistic_owner',

            'seller' => 'seller',

            default => 'customer',
        };

        if ($role === 'seller') {

            $validated['business_name'] = $request->validate([

                'business_name' => ['required', 'string', 'max:255'],

            ])['business_name'];

            $validated['selling_categories'] = $request->validate([

                'selling_categories' => ['required', 'array', 'min:1'],

            ])['selling_categories'];

            $validated['business_permit'] = $request->validate([

                'business_permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            ])['business_permit'];
        }

        if ($role === 'seller') {
            $sellerType = $request->input('seller_type');

            $sellerRules = [
                'seller_type' => [
                    'required',
                    'in:individual,sole_proprietorship,partnership,corporation',
                ],
                'seller_tin' => [
                    'required',
                    'string',
                    'regex:/^(?:[0-9]{9}|[0-9]{12}|[0-9]{3}-[0-9]{3}-[0-9]{3}(?:-[0-9]{3})?)$/',
                ],
                'pickup_region' => ['required', 'string', 'max:255'],
                'pickup_province' => ['required', 'string', 'max:255'],
                'pickup_city' => ['required', 'string', 'max:255'],
                'pickup_barangay' => ['required', 'string', 'max:255'],
                'pickup_street' => ['required', 'string', 'max:255'],
                'pickup_postal_code' => [
                    'required',
                    'regex:/^[0-9]{4}$/',
                ],
            ];

            if ($sellerType === 'sole_proprietorship') {
                $sellerRules['dti_registration_number'] = [
                    'required', 'string', 'max:100',
                ];
                $sellerRules['dti_certificate'] = [
                    'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
                ];
            }

            if (in_array($sellerType, ['partnership', 'corporation'], true)) {
                $sellerRules['seller_sec_registration_number'] = [
                    'required', 'string', 'max:100',
                ];
                $sellerRules['seller_sec_certificate'] = [
                    'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
                ];
            }

            if ($sellerType === 'corporation') {
                $sellerRules['seller_bir_form_2303'] = [
                    'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
                ];
            }

            $validated = array_merge(
                $validated,
                $request->validate($sellerRules)
            );
        }
        if ($role === 'logistic_owner') {

            $logisticRules = [

                'company_name' => ['required', 'string', 'min:2', 'max:255'],


                                'sec_registration_number' => [
                    'required',
                    'string',
                    'min:5',
                    'max:100',
                    'regex:/^[A-Za-z0-9]+(?:[- ][A-Za-z0-9]+)*$/',
                    function ($attribute, $value, $fail) {
                        $normalized = strtoupper(
                            preg_replace('/[\s-]/', '', $value)
                        );

                        $rows = DB::table('logistics')
                            ->whereNotNull('sec_registration_number')
                            ->get(['sec_registration_number']);

                        foreach ($rows as $row) {
                            $existing = strtoupper(
                                preg_replace('/[\s-]/', '', $row->sec_registration_number)
                            );

                            if ($existing === $normalized) {
                                $fail('This SEC Registration Number is already registered.');
                                return;
                            }
                        }
                    },
                ],
                'tin' => [
                    'required',
                    'string',
                    'regex:/^(?:[0-9]{9}|[0-9]{12}|[0-9]{3}-[0-9]{3}-[0-9]{3}(?:-[0-9]{3})?)$/',
                    function ($attribute, $value, $fail) {
                        $normalized = preg_replace('/[\s-]/', '', $value);

                        $rows = DB::table('logistics')
                            ->whereNotNull('tin')
                            ->get(['tin']);

                        foreach ($rows as $row) {
                            $existing = preg_replace('/[\s-]/', '', $row->tin);

                            if ($existing === $normalized) {
                                $fail('This TIN is already registered.');
                                return;
                            }
                        }
                    },
                ],
'business_region' => ['required', 'string', 'max:255'],

                'business_province' => ['required', 'string', 'max:255'],

                'business_city' => ['required', 'string', 'max:255'],

                'business_barangay' => ['required', 'string', 'max:255'],

                'business_street' => ['required', 'string', 'max:255'],

                'business_postal_code' => [
                    'required',
                    'string',
                    'regex:/^[0-9]{4}$/',
                ],

                'contact_first_name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\p{L}]+(?:[ '\x{2019}-][\p{L}]+)*$/u"],

                'contact_last_name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\p{L}]+(?:[ '\x{2019}-][\p{L}]+)*$/u"],

                'contact_position' => ['required', 'string', 'min:2', 'max:100'],

                'company_email' => ['required', 'email', 'max:255'],

                'company_phone' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],

                'service_areas' => ['required', 'string', 'max:2000'],

                'business_permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

                'sec_certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

                'bir_form_2303' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            ];

            $validated = array_merge(

                $validated,

                $request->validate($logisticRules)

            );

            $validated['contact_person'] = trim(

                $validated['contact_first_name'] . ' ' .

                    $validated['contact_last_name']

            );

            $validated['company_address'] = implode(', ', [

                $validated['business_street'],

                $validated['business_barangay'],

                $validated['business_city'],

                $validated['business_province'],

                $validated['business_region'],

                $validated['business_postal_code'],

            ]);
        }

        $userData = [

            'first_name' => $validated['first_name'],

            'middle_name' => $validated['middle_name'] ?? null,

            'last_name' => $validated['last_name'],

            'name' => $validated['last_name'] . ', ' . $validated['first_name'] . ' ' . ($validated['middle_name'] ?? ''),

            'sex' => $validated['sex'],

            'email' => $validated['email'],

            'mobile_number' => $validated['mobile_number'],

            'birthday' => $validated['birthday'],

            'age' => $validated['age'],

            'region' => $validated['region'],

            'region_name' => $request->region_name,

            'province' => $validated['province'],

            'province_name' => $request->province_name,

            'municipality' => $validated['municipality'] ?? '',

            'municipality_name' => $request->municipality_name,

            'barangay' => $validated['barangay'],

            'barangay_name' => $request->barangay_name,

            'house_number' => $validated['house_number'] ?? '',

            'street_address' => $validated['street_address'] ?? '',

            'password' => Hash::make($validated['password']),

            'phone' => $validated['mobile_number'],

            'status' => User::STATUS_PENDING,

        ];

        if ($role === 'seller') {

            $userData['business_name'] = $validated['business_name'];

            $userData['selling_categories'] = $validated['selling_categories'];
        }

        $pivotRole = match ($request->role) {

            'buyer' => 'buyer',

            'seller' => 'seller',

            'logistic' => 'logistics',

            default => null,
        };

        $uploadedPaths = [];

        try {

            // Upload customer ID verification.

            if ($request->hasFile('id_verification')) {

                $path = $request->file('id_verification')

                    ->store('customer-documents', 'supabase_private');

                if (!$path) {

                    throw new \RuntimeException('ID verification upload failed.');
                }

                $uploadedPaths[] = $path;

                $userData['id_verification'] = $path;
            }

            // Upload seller business permit.

            if ($role === 'seller' && $request->hasFile('business_permit')) {

                $path = $request->file('business_permit')

                    ->store('seller-documents', 'supabase_private');

                if (!$path) {

                    throw new \RuntimeException('Seller permit upload failed.');
                }

                $uploadedPaths[] = $path;

                $userData['business_permit'] = $path;
            }

            // Upload seller registration documents to private storage.
            $sellerDocumentPaths = [
                'dti_certificate' => null,
                'sec_certificate' => null,
                'bir_form_2303' => null,
            ];

            if ($role === 'seller') {
                $sellerDocumentFields = [
                    'dti_certificate' => 'dti_certificate',
                    'seller_sec_certificate' => 'sec_certificate',
                    'seller_bir_form_2303' => 'bir_form_2303',
                ];

                foreach ($sellerDocumentFields as $inputField => $databaseField) {
                    if (!$request->hasFile($inputField)) {
                        continue;
                    }

                    $documentPath = $request->file($inputField)
                        ->store('seller-documents', 'supabase_private');

                    if (!$documentPath) {
                        throw new \RuntimeException(
                            "Seller document upload failed: {$inputField}"
                        );
                    }

                    $uploadedPaths[] = $documentPath;
                    $sellerDocumentPaths[$databaseField] = $documentPath;
                }
            }
            // Upload logistics business permit.

            $logisticPermit = null;

            if ($role === 'logistic_owner' && $request->hasFile('business_permit')) {

                $logisticPermit = $request->file('business_permit')

                    ->store('logistic-documents', 'supabase_private');

                if (!$logisticPermit) {

                    throw new \RuntimeException('Logistics permit upload failed.');
                }

                $uploadedPaths[] = $logisticPermit;
            }

            $logisticSecCertificate = null;

            $logisticBirForm2303 = null;


        if ($role === 'logistic_owner') {

                $logisticSecCertificate = $request->file('sec_certificate')

                    ->store('logistic-documents', 'supabase_private');

                if (!$logisticSecCertificate) {

                    throw new \RuntimeException('SEC certificate upload failed.');
                }

                $uploadedPaths[] = $logisticSecCertificate;

                $logisticBirForm2303 = $request->file('bir_form_2303')

                    ->store('logistic-documents', 'supabase_private');

                if (!$logisticBirForm2303) {

                    throw new \RuntimeException('BIR Form 2303 upload failed.');
                }

                $uploadedPaths[] = $logisticBirForm2303;
            }

            $geocoded = $role === 'logistic_owner'

                ? $this->geocodeAddress(

                    $validated['api_address'] ?? $validated['company_address']

                )

                : null;

            $user = DB::transaction(function () use (

                $userData,

                $pivotRole,

                $role,

                $validated,

                $geocoded,

                $logisticPermit,

                $logisticSecCertificate,

                $logisticBirForm2303,
                $sellerDocumentPaths

            ) {

                $user = User::create($userData);

                if ($pivotRole) {

                    $roleModel = Role::where('name', $pivotRole)->firstOrFail();

                    $user->roles()->syncWithoutDetaching([

                        $roleModel->id,

                    ]);
                }


                        if ($role === 'seller') {
                    \App\Models\SellerRegistrationDetail::create([
                        'user_id' => $user->id,
                        'seller_type' => $validated['seller_type'],
                        'tin' => preg_replace('/[^0-9]/', '', $validated['seller_tin']),
                        'dti_registration_number' => $validated['dti_registration_number'] ?? null,
                        'sec_registration_number' => $validated['seller_sec_registration_number'] ?? null,
                        'dti_certificate' => $sellerDocumentPaths['dti_certificate'],
                        'sec_certificate' => $sellerDocumentPaths['sec_certificate'],
                        'bir_form_2303' => $sellerDocumentPaths['bir_form_2303'],
                        'pickup_region' => $validated['pickup_region'],
                        'pickup_province' => $validated['pickup_province'],
                        'pickup_city' => $validated['pickup_city'],
                        'pickup_barangay' => $validated['pickup_barangay'],
                        'pickup_street' => $validated['pickup_street'],
                        'pickup_postal_code' => $validated['pickup_postal_code'],
                    ]);
                }
if ($role === 'logistic_owner') {

                    Logistic::create([

                        'owner_user_id' => $user->id,

                        'company_name' => $validated['company_name'],

                        'sec_registration_number' => $validated['sec_registration_number'],

                        'tin' => $validated['tin'],

                        'business_region' => $validated['business_region'],

                        'business_province' => $validated['business_province'],

                        'business_city' => $validated['business_city'],

                        'business_barangay' => $validated['business_barangay'],

                        'business_street' => $validated['business_street'],

                        'business_postal_code' => $validated['business_postal_code'],

                        'contact_first_name' => $validated['contact_first_name'],

                        'contact_last_name' => $validated['contact_last_name'],

                        'contact_position' => $validated['contact_position'],

                        'contact_person' => $validated['contact_person'],

                        'email' => $validated['company_email'],

                        'phone' => $validated['company_phone'],

                        'service_areas' => $validated['service_areas'],

                        'address' => $validated['company_address'],

                        'api_address' => $validated['api_address'] ?? null,

                        'latitude' => $geocoded['lat'] ?? null,

                        'longitude' => $geocoded['lng'] ?? null,

                        'status' => 'pending',

                        'business_permit' => $logisticPermit,

                        'sec_certificate' => $logisticSecCertificate,

                        'bir_form_2303' => $logisticBirForm2303,

                    ]);
                }

                return $user;
            });
        } catch (\Throwable $e) {

            // Clean up only documents uploaded during this attempt.

            foreach ($uploadedPaths as $path) {

                try {

                    Storage::disk('supabase_private')->delete($path);
                } catch (\Throwable $cleanupException) {

                    report($cleanupException);
                }
            }

            if (

                $e instanceof QueryException

                && $this->isDuplicateEmailViolation($e)

            ) {

                return $this->backWithDuplicateEmail($request);
            }

            throw $e;
        }

        try {

            Mail::to($user->email)->queue(new RegistrationNotification($user));
        } catch (\Throwable $e) {

            logger()->error('Failed to send registration notification: ' . $e->getMessage());
        }

        // Buyer and Seller notifications are sent only after successful OTP.

        // Logistics applications do not currently require email OTP.


        if ($role === 'logistic_owner') {

            try {

                $admins = User::whereHas('roles', function ($query) {

                    $query->whereIn('name', ['admin', 'superadmin']);
                })->get();

                foreach ($admins as $admin) {

                    Mail::to($admin->email)->queue(new NewRegistrationAdmin($user));
                }
            } catch (\Throwable $e) {

                logger()->error('Failed to send logistics admin notification', [

                    'user_id' => $user->id,

                    'error' => $e->getMessage(),

                ]);
            }
        }

        $request->session()->put('registration_email', $user->email);

        if ($role === 'customer') {

            $request->session()->put('buyer_otp_user_id', $user->id);

            try {

                app(BuyerEmailVerificationController::class)->sendOtp($user);
            } catch (\Throwable $e) {

                logger()->error('Failed to send initial Buyer OTP', [

                    'user_id' => $user->id,

                    'error' => $e->getMessage(),

                ]);

                return redirect()->route('buyer.email.verify')

                    ->withErrors([

                        'otp' => 'We could not send your verification code. Please try resending it.',

                    ]);
            }

            return redirect()->route('buyer.email.verify')

                ->with('success', 'We sent a 6-digit verification code to your email address.');
        }

        if ($role === 'seller') {

            $request->session()->put('seller_otp_user_id', $user->id);

            try {

                app(SellerEmailVerificationController::class)->sendOtp($user);
            } catch (\Throwable $e) {

                logger()->error('Failed to send initial Seller OTP', [

                    'user_id' => $user->id,

                    'error' => $e->getMessage(),

                ]);

                return redirect()->route('seller.email.verify')

                    ->withErrors([

                        'otp' => 'We could not send your verification code. Please try resending it.',

                    ]);
            }

            return redirect()->route('seller.email.verify')

                ->with('success', 'We sent a 6-digit verification code to your email address.');
        }

        return redirect(route('login'))

            ->with('success', 'Your registration has been submitted. Please wait for the administrator\'s approval, which will be sent to your email.');
    }

    private function normalizeMobileNumberInput(Request $request): void

    {

        $mobile = trim((string) $request->input('mobile_number', ''));

        // Convert +639XXXXXXXXX to 09XXXXXXXXX

        if (preg_match('/^\+63(9[0-9]{9})$/', $mobile, $matches)) {

            $mobile = '0' . $matches[1];
        }

        // Convert 639XXXXXXXXX to 09XXXXXXXXX

        elseif (preg_match('/^63(9[0-9]{9})$/', $mobile, $matches)) {

            $mobile = '0' . $matches[1];
        }

        $request->merge([

            'mobile_number' => $mobile,

        ]);
    }
}
