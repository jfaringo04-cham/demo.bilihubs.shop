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

        return $next($request);
    }
}
