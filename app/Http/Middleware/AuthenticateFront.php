<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateFront
{
    public function handle($request, Closure $next)
    {
        if (!Auth::guard('front')->check()) {
            return redirect('/front/login');
        }

        return $next($request);
    }
}
