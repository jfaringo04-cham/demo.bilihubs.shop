<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\BuyerEmailOtp;
use App\Mail\NewRegistrationAdmin;
use App\Services\AdminRegistrationNotification;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SellerEmailVerificationController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    public function show(Request $request)
    {
        $user = $this->pendingSeller($request);

        return view('auth.verify-buyer-email', [
            'email' => $user->email,
            'verifyRoute' => 'seller.email.verify.submit',
            'resendRoute' => 'seller.email.otp.resend',
        ]);
    }

    public function sendOtp(User $user): void
    {
        $otp = DB::transaction(function () use ($user) {
            $lockedUser = User::whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$lockedUser->isPending() ||
                $lockedUser->email_verified_at !== null ||
                !$lockedUser->roles()->where('name', 'seller')->exists()
            ) {
                return null;
            }

            $record = EmailVerificationOtp::where(
                'user_id',
                $lockedUser->id
            )->first();

            if (
                $record &&
                now()->lessThan(
                    $record->last_sent_at->copy()->addSeconds(
                        self::RESEND_COOLDOWN_SECONDS
                    )
                )
            ) {
                return null;
            }

            $code = (string) random_int(100000, 999999);

            EmailVerificationOtp::updateOrCreate(
                ['user_id' => $lockedUser->id],
                [
                    'otp_hash' => Hash::make($code),
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(
                        self::OTP_EXPIRY_MINUTES
                    ),
                    'last_sent_at' => now(),
                ]
            );

            return $code;
        });

        if ($otp !== null) {
            try {
                Mail::to($user->email)->send(
                    new BuyerEmailOtp($otp)
                );
            } catch (\Throwable $e) {
                DB::transaction(function () use ($user, $otp) {
                    $lockedUser = User::whereKey($user->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $record = EmailVerificationOtp::where(
                        'user_id',
                        $lockedUser->id
                    )->first();

                    if (
                        $record &&
                        Hash::check($otp, $record->otp_hash)
                    ) {
                        $record->delete();
                    }
                });

                throw $e;
            }
        }
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->pendingSeller($request);

        $error = DB::transaction(function () use ($user, $validated) {
            $lockedUser = User::whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !$lockedUser->isPending() ||
                $lockedUser->email_verified_at !== null ||
                !$lockedUser->roles()->where('name', 'seller')->exists()
            ) {
                return 'This account is not eligible for verification.';
            }

            $record = EmailVerificationOtp::where(
                'user_id',
                $lockedUser->id
            )->lockForUpdate()->first();

            if (!$record) {
                return 'Please request a verification code.';
            }

            if (now()->greaterThanOrEqualTo($record->expires_at)) {
                return 'The code has expired. Request a new code.';
            }

            if ($record->attempts >= self::MAX_ATTEMPTS) {
                return 'Too many attempts. Request a new code.';
            }

            if (!Hash::check($validated['otp'], $record->otp_hash)) {
                $record->increment('attempts');

                return $record->attempts >= self::MAX_ATTEMPTS
                    ? 'Too many attempts. Request a new code.'
                    : 'Invalid verification code.';
            }

            // Seller remains pending until Admin approval.
            $lockedUser->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $record->delete();

            app(AdminRegistrationNotification::class)->send($lockedUser, 'Seller');

            return null;
        });

        if ($error !== null) {
            throw ValidationException::withMessages([
                'otp' => $error,
            ]);
        }

        // Notify admins only after email verification succeeds.
        // This is outside the OTP transaction so mail failures cannot undo verification.
        try {
            $admins = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'superadmin']);
            })->get();

            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(new NewRegistrationAdmin($user->fresh()));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify admins after Seller OTP verification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $request->session()->forget('seller_otp_user_id');

        return redirect()->route('login')->with(
            'success',
            'Email verified successfully! Your Seller application is now awaiting Admin approval.'
        );
    }

    public function resend(Request $request)
    {
        $user = $this->pendingSeller($request);

        $record = EmailVerificationOtp::where(
            'user_id',
            $user->id
        )->first();

        if (
            $record &&
            now()->lessThan(
                $record->last_sent_at->copy()->addSeconds(
                    self::RESEND_COOLDOWN_SECONDS
                )
            )
        ) {
            throw ValidationException::withMessages([
                'otp' => 'Please wait 60 seconds before requesting another code.',
            ]);
        }

        $this->sendOtp($user);

        return back()->with(
            'success',
            'Your resend request has been processed. Check your email.'
        );
    }

    private function pendingSeller(Request $request): User
    {
        $userId = $request->session()->get('seller_otp_user_id');

        $user = $userId
            ? User::whereKey($userId)
            ->whereHas(
                'roles',
                fn($query) => $query->where('name', 'seller')
            )
            ->first()
            : null;

        if (
            !$user ||
            !$user->isPending() ||
            $user->email_verified_at !== null
        ) {
            abort(403, 'No pending Seller email verification found.');
        }

        return $user;
    }
}
