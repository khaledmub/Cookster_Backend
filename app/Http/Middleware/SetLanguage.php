<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class SetLanguage
{
    public function handle($request, Closure $next)
    {
        // 1. Get the raw header
        $header = $request->header('Accept-Language', 'en');

        // 2. Parse the header: 
        // This regex grabs the first 2-character language code (e.g., "en" from "en-GB")
        // or you can just explode by comma and dash.
        $language = explode(',', explode(';', $header)[0])[0];
        $language = str_replace('_', '-', $language);
        $language = explode('-', $language)[0]; 

        // 3. Optional: Validate against your supported languages
        $supportedLanguages = ['en', 'ar']; // Add your supported codes here
        if (!in_array($language, $supportedLanguages)) {
            $language = 'en';
        }

        App::setLocale($language);

        return $next($request);
    }
}
