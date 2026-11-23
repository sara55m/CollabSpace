<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        //get the preferred language form request header
        $locale = $request->getPreferredLanguage(config('app.supported_locales'))
            ?? config('app.locale');

        app()->setLocale($locale);

        return $next($request);
    }
}
