<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\BuyerEmailOtp;
use App\Models\EmailVerificationOtp;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RiderEmailVerificationController extends Controller
{
    public const SESSION_KEY = 'rider_otp_user_id';

    private const EXPIRY_MINUTES = 5;
    private const RESEND_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    public function begin(Request $request, User $user)
    {
        $request->session()->put(self::SESSION_KEY, $user->id);

        return $this->sendCode($user, false);
    }

    public function show(Request $request)
    {
        return view('auth.verify-buyer-email', [
            'email' => $this->pendingRider($request)->email,
            'verifyRoute' => 'rider.email.verify.submit',
            'resendRoute' => 'rider.email.otp.resend',
            'approvalMessage' => 'After verification, your logistics company will review your application.',
        ]);
    }

    public function resend(Request $request)
    {
        return $this->sendCode($this->pendingRider($request), true);
    }

    public function verify(Request $request)
    {
        $user = $this->pendingRider($request);
        $validated = $request->validate(['otp' => ['required', 'digits:6']]);

        $error = DB::transaction(function () use ($user, $validated) {
            $rider = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($this->eligible($rider), 403);

            $record = EmailVerificationOtp::where('user_id', $rider->id)->first();
            if (!$record || now()->greaterThanOrEqualTo($record->expires_at)) {
                return 'Request a new verification code.';
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

            $rider->forceFill(['email_verified_at' => now()])->save();
            $record->delete();

            $company = $rider->logistic;
            if ($company?->owner_user_id) {
                Notification::create([
                    'user_id' => $company->owner_user_id,
                    'title' => 'New Rider Application',
                    'message' => $rider->name . ' has verified their email and is ready for application review.',
                    'type' => 'logistic',
                    'link' => route('logistic.applications'),
                ]);
            }

            return null;
        });

        if ($error !== null) {
            return redirect()->route('rider.email.verify')->withErrors(['otp' => $error]);
        }

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')
            ->with('success', 'Email verified. Your rider application is awaiting logistics approval.');
    }

    private function sendCode(User $user, bool $resend)
    {
        $redirect = redirect()->route('rider.email.verify');
        $code = (string) random_int(100000, 999999);
        $hash = Hash::make($code);

        $email = DB::transaction(function () use ($user, $hash) {
            $rider = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($this->eligible($rider), 403);

            $record = EmailVerificationOtp::where('user_id', $rider->id)->first();
            if ($record && now()->lt($record->last_sent_at->copy()->addSeconds(self::RESEND_SECONDS))) {
                return null;
            }

            EmailVerificationOtp::updateOrCreate(['user_id' => $rider->id], [
                'otp_hash' => $hash,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
                'last_sent_at' => now(),
            ]);

            return $rider->email;
        });

        if ($email === null) {
            return $resend
                ? $redirect->withErrors(['otp' => 'Please wait 60 seconds before requesting another code.'])
                : $redirect;
        }

        try {
            Mail::to($email)->send(new BuyerEmailOtp(
                $code,
                'After verifying your email, your application will be reviewed by your logistics company.',
            ));
        } catch (\Throwable $e) {
            EmailVerificationOtp::where('user_id', $user->id)->where('otp_hash', $hash)->delete();
            report($e);

            return $redirect->withErrors(['otp' => 'Unable to send the code. Please try resending.']);
        }

        return $redirect->with('success', 'Verification code sent. Check your email.');
    }

    private function pendingRider(Request $request): User
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $rider = $id ? User::find($id) : null;

        abort_unless($rider && $this->eligible($rider), 403);

        return $rider;
    }

    private function eligible(User $rider): bool
    {
        return $rider->needsRiderEmailVerification()
            && $rider->isPending()
            && $rider->logistic_status === 'pending'
            && $rider->isRider();
    }
}
