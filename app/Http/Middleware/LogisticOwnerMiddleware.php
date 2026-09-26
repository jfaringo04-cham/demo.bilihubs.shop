<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogisticOwnerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->isLogisticOwner()) {
            abort(403, 'Only logistics owners can access this page.');
        }

        $user = Auth::user();

        if (!$user->isActive()) {
            abort(403, 'Your logistics account is still awaiting admin approval.');
        }

        $logistic = $user->ownedLogistic;

        if (!$logistic || $logistic->status !== 'active') {
            abort(403, 'Your logistics company is not active.');
        }

        return $next($request);
    }
}
