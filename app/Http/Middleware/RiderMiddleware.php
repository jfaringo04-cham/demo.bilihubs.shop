<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RiderMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->isRider()) {
            abort(403, 'Only riders can access this page.');
        }

        $user = Auth::user();

        if (!$user->isActive() || $user->logistic_status !== 'approved') {
            abort(403, 'Your rider account is still awaiting logistics approval.');
        }

        return $next($request);
    }
}
