<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user()->load('roles');

        $defaultAddress = null;
        $orderCount = 0;
        $addressCount = 0;

        if ($user->isCustomer()) {
            $defaultAddress = $user->addresses()
                ->orderByDesc('is_default')
                ->latest()
                ->first();

            $orderCount = $user->orders()->count();
            $addressCount = $user->addresses()->count();
        }

        return view('profile.edit', compact(
            'user',
            'defaultAddress',
            'orderCount',
            'addressCount'
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'remove_profile_photo' => ['nullable', 'boolean'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->address = $validated['address'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Remove Profile Photo
        |--------------------------------------------------------------------------
        */
        if ($request->boolean('remove_profile_photo')) {
            if ($user->profile_photo_path) {
                $this->deleteProfilePhoto($user->profile_photo_path);
            }

            $user->profile_photo_path = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Upload New Profile Photo to S3
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                $this->deleteProfilePhoto($user->profile_photo_path);
            }

            $path = $request
                ->file('profile_photo')
                ->store('profile-pictures', 's3');

            if (!$path) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'profile_photo' => 'Unable to upload profile photo. Please try again.',
                    ]);
            }

            $user->profile_photo_path = $path;
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validateWithBag('passwordUpdate', [
            'current_password' => [
                'required',
                'current_password',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $user = Auth::user();

        $user->password = $validated['password'];
        $user->save();

        return back()->with(
            'password_success',
            'Password changed successfully.'
        );
    }

    private function deleteProfilePhoto(?string $path): void
    {
        if (!$path) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete from S3
        |--------------------------------------------------------------------------
        |
        | New profile photos are stored here.
        |
        */
        try {
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        /*
        |--------------------------------------------------------------------------
        | Delete old local copy if present
        |--------------------------------------------------------------------------
        |
        | This keeps compatibility with profile photos uploaded before we moved
        | profile storage to S3.
        |
        */
        try {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
