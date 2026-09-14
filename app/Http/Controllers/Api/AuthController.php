<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'mobile_number' => 'nullable|string|digits:11',
            'role' => 'required|in:customer,seller,rider',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:1',
            'last_name' => 'nullable|string|max:255',
            'sex' => 'nullable|in:Male,Female',
            'birthday' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'mobile_number' => $request->mobile_number,
            'role' => $request->role,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'sex' => $request->sex,
            'birthday' => $request->birthday,
            'age' => $request->age,
            'status' => in_array($request->role, ['seller', 'rider']) ? User::STATUS_PENDING : User::STATUS_APPROVED,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();

        if ($user->status === User::STATUS_REJECTED) {
            Auth::logout();
            return response()->json([
                'message' => 'Your account has been rejected. Reason: ' . $user->rejection_reason,
            ], 403);
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            Auth::logout();
            return response()->json([
                'message' => 'Your account is suspended. Please contact support.',
            ], 403);
        }

        $deviceName = $request->device_name ?? 'API Token';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user->load('hub', 'logistic'),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $googleUser = Socialite::driver('google')->userFromToken($request->access_token);

            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                if (!$user->canLogin()) {
                    return response()->json([
                        'message' => 'Account pending or not active.',
                    ], 403);
                }

                $token = $user->createToken('google-login')->plainTextToken;

                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]);
            }

            return response()->json([
                'message' => 'No account found. Please complete registration.',
                'social_user' => [
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                ],
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Google login failed.',
            ], 401);
        }
    }

    public function facebookLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $facebookUser = Socialite::driver('facebook')->userFromToken($request->access_token);

            $user = User::where('email', $facebookUser->getEmail())->first();

            if ($user) {
                if (!$user->canLogin()) {
                    return response()->json([
                        'message' => 'Account pending or not active.',
                    ], 403);
                }

                $token = $user->createToken('facebook-login')->plainTextToken;

                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]);
            }

            return response()->json([
                'message' => 'No account found. Please complete registration.',
                'social_user' => [
                    'name' => $facebookUser->getName(),
                    'email' => $facebookUser->getEmail(),
                    'provider' => 'facebook',
                    'provider_id' => $facebookUser->getId(),
                ],
            ], 202);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Facebook login failed.',
            ], 401);
        }
    }

    public function refresh(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        $token = $request->user()->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices.',
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('hub', 'logistic'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'mobile_number' => 'nullable|string|digits:11|unique:users,mobile_number,' . $user->id,
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:1',
            'last_name' => 'nullable|string|max:255',
            'sex' => 'nullable|in:Male,Female',
            'birthday' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:120',
            'house_number' => 'nullable|string|max:50',
            'street_address' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'barangay_name' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'municipality_name' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'province_name' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'region_name' => 'nullable|string|max:255',
            'business_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only([
            'name', 'mobile_number', 'first_name', 'middle_name', 'last_name',
            'sex', 'birthday', 'age', 'house_number', 'street_address',
            'barangay', 'barangay_name', 'municipality', 'municipality_name',
            'province', 'province_name', 'region', 'region_name', 'business_name',
        ]));

        return response()->json([
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all tokens except current
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Password updated. Other sessions logged out.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Use Laravel's built-in password reset
        $status = \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email.',
            ]);
        }

        return response()->json([
            'message' => 'Unable to send reset link.',
        ], 500);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));
                $user->save();

                // Revoke all tokens
                $user->tokens()->delete();
            }
        );

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired reset token.',
        ], 400);
    }

    public function registerDevice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_token' => 'required|string',
            'platform' => 'required|in:ios,android,web',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $request->user()->update([
            'device_token' => $request->device_token,
            'device_platform' => $request->platform,
        ]);

        return response()->json([
            'message' => 'Device registered for push notifications.',
        ]);
    }
}