<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class SetLanguage
{
    public function handle($request, Closure $next)
    {
        $language = $request->header('Accept-Language', 'en'); // Default to English
        App::setLocale($language);

        return $next($request);
    }
}
