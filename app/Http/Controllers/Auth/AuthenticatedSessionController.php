<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if ($user->needsRiderEmailVerification() && $user->isRider()
            && $user->isPending() && $user->logistic_status === 'pending') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return app(RiderEmailVerificationController::class)->begin($request, $user);
        }

        /*
        |--------------------------------------------------------------------------
        | Buyer Email Verification
        |--------------------------------------------------------------------------
        | Only pending Buyers may complete OTP verification.
        | Successful verification automatically activates the account.
        |--------------------------------------------------------------------------
        */

        if (
            $user->isCustomer() &&
            $user->email_verified_at === null &&
            (
                $user->isPending() ||
                $user->isActive()
            )
        ) {
            $buyerId = $user->id;

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $request->session()->put(
                'buyer_otp_user_id',
                $buyerId
            );

            try {
                app(BuyerEmailVerificationController::class)
                    ->sendOtp($user);

                return redirect()
                    ->route('buyer.email.verify')
                    ->with(
                        'success',
                        'Please verify your email using the OTP sent to you.'
                    );
            } catch (\Throwable $e) {
                report($e);

                return redirect()
                    ->route('buyer.email.verify')
                    ->withErrors([
                        'otp' => 'Unable to send OTP. Please try resending.',
                    ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Account Status and Approval
        |--------------------------------------------------------------------------
        */

        if (!$user->canLogin()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = match ($user->status) {
                User::STATUS_PENDING => match (true) {
                    $user->isSeller() =>
                    'Your Seller registration is awaiting Admin approval.',

                    $user->isLogisticOwner() =>
                    'Your Logistics registration is awaiting Admin approval.',

                    $user->isRider() =>
                    'Your Rider application is awaiting Logistics approval.',

                    default =>
                    'Your account registration is incomplete.',
                },

                User::STATUS_SUSPENDED =>
                'Your account has been suspended. Please contact support.',

                User::STATUS_DEACTIVATED =>
                'Your account has been deactivated.',

                User::STATUS_REJECTED =>
                'Your account application has been rejected.',

                default =>
                'Your account is not eligible to log in.',
            };

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Additional Logistics Company Check
        |--------------------------------------------------------------------------
        */

        if ($user->isLogisticOwner()) {
            $logistic = $user->ownedLogistic;

            if (!$logistic || $logistic->status !== 'active') {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => 'Your logistics company is not active.',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Successful Login
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();

        if ($user->isAdmin()) {
            return redirect()->intended(
                route('admin.dashboard')
            );
        }

        if ($user->isLogisticOwner()) {
            return redirect()->intended(
                route('logistic.home')
            );
        }

        if ($user->isSeller()) {
            return redirect()->intended(
                route('seller.dashboard')
            );
        }

        if ($user->isRider()) {
            return redirect()->intended(
                route('rider.dashboard')
            );
        }

        if ($user->isCustomer()) {
            return redirect()->intended(
                route('home')
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        throw ValidationException::withMessages([
            'email' => 'No valid account role was found.',
        ]);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
