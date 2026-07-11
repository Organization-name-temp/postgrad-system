<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the app locale from the request's Accept-Language, so server-generated
 * messages (validation, auth) come back in the caller's language. The SPA sends
 * the header from its current UI language, which is seeded from the user's
 * persisted `preferred_locale` (ticket 06).
 */
class SetLocaleFromRequest
{
    /** Locales the system supports; the negotiated best match wins. */
    private const array SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($request->getPreferredLanguage(self::SUPPORTED));

        return $next($request);
    }
}
