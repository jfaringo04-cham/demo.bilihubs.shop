<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\NewRegistrationAdmin;
use App\Mail\RegistrationNotification;
use App\Models\Category;
use App\Models\User;
use App\Models\Logistic;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function create()
    {
        $categories = Category::all();
        return view('auth.register', compact('categories'));
    }

    public function createRiderApplication()
    {
        $logistics = \App\Models\Logistic::where('status', 'active')->get();
        return view('auth.apply-rider', compact('logistics'));
    }

    public function storeRiderApplication(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:1'],
            'last_name' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile_number' => ['required', 'string', 'digits:11'],
            'birthday' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'region' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'vehicle_type' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255'],
            'or_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'cr_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'logistic_id' => ['required', 'exists:logistics,id'],
        ]);

        $logistic = \App\Models\Logistic::where('id', $validated['logistic_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $userData = [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'],
            'last_name' => $validated['last_name'],
            'name' => $validated['last_name'] . ', ' . $validated['first_name'] . ' ' . $validated['middle_name'],
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'],
            'birthday' => $validated['birthday'],
            'age' => $validated['age'],
            'province' => $validated['province'],
            'municipality' => $validated['municipality'],
            'barangay' => $validated['barangay'],
            'house_number' => $validated['house_number'] ?? '',
            'street_address' => $validated['street_address'] ?? '',
            'password' => Hash::make($validated['password']),
            'role' => 'rider',
            'phone' => $validated['mobile_number'],
            'status' => User::STATUS_PENDING,
            'vehicle_type' => $validated['vehicle_type'],
            'license_number' => $validated['license_number'],
            'logistic_id' => $logistic->id,
            'logistic_status' => 'pending',
        ];

        if ($request->hasFile('id_verification')) {
            $userData['id_verification'] = $request->file('id_verification')->store('customer-documents', 'public');
        }

        if ($request->hasFile('or_document')) {
            $userData['or_document'] = $request->file('or_document')->store('rider-documents', 'public');
        }

        if ($request->hasFile('cr_document')) {
            $userData['cr_document'] = $request->file('cr_document')->store('rider-documents', 'public');
        }

        $user = User::create($userData);

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

        return redirect(route('login'))->with('success', 'Your rider application has been submitted to ' . $logistic->company_name . '. Please wait for approval.');
    }

    private function geocodeAddress($address)
    {
        $apiKey = config('services.googlemaps.key');
        if (!$apiKey || !$address) {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;

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
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:1'],
            'last_name' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile_number' => ['required', 'string', 'digits:11'],
            'birthday' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'role' => ['required', 'in:buyer,seller,logistic'],
            'region' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'id_verification' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'selling_categories' => ['nullable', 'array'],
            'business_permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'string', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'api_address' => ['nullable', 'string', 'max:500'],
        ]);

        $role = $request->role === 'buyer' ? 'customer' : $request->role;

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

        if ($role === 'logistic') {
            $validated['company_name'] = $request->validate([
                'company_name' => ['required', 'string', 'max:255'],
            ])['company_name'];

            $validated['business_permit'] = $request->validate([
                'business_permit' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ])['business_permit'];

            $validated['company_email'] = $request->validate([
                'company_email' => ['required', 'string', 'email', 'max:255'],
            ])['company_email'];
        }

        $userData = [
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'],
            'last_name' => $validated['last_name'],
            'name' => $validated['last_name'] . ', ' . $validated['first_name'] . ' ' . $validated['middle_name'],
            'sex' => $validated['sex'],
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'],
            'birthday' => $validated['birthday'],
            'age' => $validated['age'],
            'region' => $validated['region'],
            'region_name' => $request->region_name,
            'province' => $validated['province'],
            'province_name' => $request->province_name,
            'municipality' => $validated['municipality'],
            'municipality_name' => $request->municipality_name,
            'barangay' => $validated['barangay'],
            'barangay_name' => $request->barangay_name,
            'house_number' => $validated['house_number'],
            'street_address' => $validated['street_address'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'phone' => $validated['mobile_number'],
            'status' => User::STATUS_PENDING,
        ];

        if ($role === 'seller') {
            $userData['business_name'] = $validated['business_name'];
            $userData['selling_categories'] = $validated['selling_categories'];
        }

        if ($request->hasFile('id_verification')) {
            $userData['id_verification'] = $request->file('id_verification')->store('customer-documents', 'public');
        }

        if ($role === 'seller' && $request->hasFile('business_permit')) {
            $userData['business_permit'] = $request->file('business_permit')->store('seller-documents', 'public');
        }

        $user = User::create($userData);

        if ($role === 'logistic') {
            $geocoded = $this->geocodeAddress($validated['api_address'] ?? $validated['company_address']);

            Logistic::create([
                'owner_user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'contact_person' => $validated['contact_person'],
                'email' => $validated['company_email'],
                'phone' => $validated['company_phone'],
                'address' => $validated['company_address'],
                'api_address' => $validated['api_address'] ?? null,
                'latitude' => $geocoded['lat'] ?? null,
                'longitude' => $geocoded['lng'] ?? null,
                'status' => 'pending',
                'business_permit' => $request->hasFile('business_permit') ? $request->file('business_permit')->store('logistic-documents', 'public') : null,
            ]);
        }

        try {
            Mail::to($user->email)->send(new RegistrationNotification($user));
        } catch (\Throwable $e) {
            logger()->error('Failed to send registration notification: ' . $e->getMessage());
        }

        try {
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new NewRegistrationAdmin($user));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send admin notification: ' . $e->getMessage());
        }

        return redirect(route('login'))->with('success', 'Your registration has been submitted. Please wait for the administrator\'s approval, which will be sent to your email.');
    }
}
