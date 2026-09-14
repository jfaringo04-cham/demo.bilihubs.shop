<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('The provided credentials do not match our records.'),
            ]);
        }

        $user = Auth::user();

        if (!$user->canLogin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = match ($user->status) {
                'pending' => 'Your account is still pending admin approval. You will be notified by email once approved.',
                'suspended' => 'Your account has been suspended. Please contact support.',
                'deactivated' => 'Your account has been deactivated.',
                'rejected' => 'Your registration application was rejected.',
                default => 'Your account is not active.',
            };

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->isLogisticOwner()) {
            return redirect()->intended(route('logistic.home'));
        }

        if ($user->isSeller()) {
            return redirect()->intended(route('seller.dashboard'));
        }

        if ($user->isRider()) {
            return redirect()->intended(route('rider.dashboard'));
        }

        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
