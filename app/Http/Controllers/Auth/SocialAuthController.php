<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Models\Size;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                if (!$user->canLogin()) {
                    return redirect()->route('login')->with('error', 'Your account is still pending or not active. Please wait for admin approval.');
                }

                Auth::login($user);
                if ($user->isLogisticOwner()) {
                    return redirect()->intended(route('logistic.home'));
                }
                if ($user->isSeller()) {
                    return redirect()->intended(route('seller.dashboard'));
                }
                return redirect()->intended(route('home'));
            }

            session([
                'social_user' => [
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                ]
            ]);

            return redirect()->route('social.register');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Google login failed. Please try again.');
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();

            $user = User::where('email', $facebookUser->getEmail())->first();

            if ($user) {
                if (!$user->canLogin()) {
                    return redirect()->route('login')->with('error', 'Your account is still pending or not active. Please wait for admin approval.');
                }

                Auth::login($user);
                if ($user->isLogisticOwner()) {
                    return redirect()->intended(route('logistic.home'));
                }
                if ($user->isSeller()) {
                    return redirect()->intended(route('seller.dashboard'));
                }
                return redirect()->intended(route('home'));
            }

            session([
                'social_user' => [
                    'name' => $facebookUser->getName(),
                    'email' => $facebookUser->getEmail(),
                    'provider' => 'facebook',
                    'provider_id' => $facebookUser->getId(),
                ]
            ]);

            return redirect()->route('social.register');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Facebook login failed. Please try again.');
        }
    }

    public function showRegistrationForm()
    {
        if (!session('social_user')) {
            return redirect()->route('login');
        }

        $categories = Category::all();
        $sizes = Size::all();

        return view('auth.social-register', compact('categories', 'sizes'));
    }

    public function storeRegistration()
    {
        $request = request();

        $socialUser = session('social_user');

        if (!$socialUser) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:1'],
            'last_name' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'mobile_number' => ['required', 'string', 'digits:11'],
            'birthday' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'role' => ['required', 'in:customer,seller'],
            'region' => ['required', 'string', 'max:255'],
            'region_name' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'province_name' => ['required', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'municipality_name' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'barangay_name' => ['required', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'id_verification' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'selling_categories' => ['nullable', 'array'],
            'business_permit' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $idPath = null;
        if ($request->hasFile('id_verification')) {
            $idPath = $request->file('id_verification')->store('customer-documents', 'public');
        }

        $businessPermitPath = null;
        if ($request->hasFile('business_permit')) {
            $businessPermitPath = $request->file('business_permit')->store('seller-documents', 'public');
        }

        $name = $validated['last_name'] . ', ' . $validated['first_name'] . ' ' . $validated['middle_name'];

        $user = User::create([
            'name' => $name,
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'],
            'last_name' => $validated['last_name'],
            'sex' => $validated['sex'],
            'email' => $socialUser['email'],
            'mobile_number' => $validated['mobile_number'],
            'birthday' => $validated['birthday'],
            'age' => $validated['age'],
            'role' => $validated['role'],
            'region' => $validated['region'],
            'region_name' => $validated['region_name'],
            'province' => $validated['province'],
            'province_name' => $validated['province_name'],
            'municipality' => $validated['municipality'],
            'municipality_name' => $validated['municipality_name'],
            'barangay' => $validated['barangay'],
            'barangay_name' => $validated['barangay_name'],
            'house_number' => $validated['house_number'],
            'street_address' => $validated['street_address'],
            'id_verification' => $idPath,
            'business_name' => $validated['business_name'] ?? null,
            'selling_categories' => $validated['selling_categories'] ?? null,
            'business_permit' => $businessPermitPath,
            'password' => Hash::make(Str::random(16)),
            'status' => User::STATUS_PENDING,
            'email_verified_at' => now(),
        ]);

        session()->forget('social_user');

        try {
            Mail::to($user->email)->send(new \App\Mail\RegistrationNotification($user));
        } catch (\Throwable $e) {
            logger()->error('Failed to send registration notification: ' . $e->getMessage());
        }

        try {
            $admins = \App\Models\User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new \App\Mail\NewRegistrationAdmin($user));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send admin notification: ' . $e->getMessage());
        }

        return redirect()->route('login')->with('success', 'Your registration has been submitted. Please wait for the administrator\'s approval, which will be sent to your email.');
    }
}
