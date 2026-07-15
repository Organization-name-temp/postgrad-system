<?php

use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum SPA auth: first-party requests from stateful domains (the Vite
        // dev origin) are authenticated via the session cookie + CSRF (Q10).
        $middleware->statefulApi();

        // Localize server messages from the request's Accept-Language (ticket 06).
        $middleware->appendToGroup('api', SetLocaleFromRequest::class);

        // API-only backend: there is no `login` route. Laravel's default guest
        // redirect (`fn () => route('login')`) is evaluated *eagerly* by the auth
        // middleware for a non-JSON request, throwing "Route [login] not defined."
        // before the exception handler ever runs. Never redirect a guest — the
        // handler renders a clean 401 for API requests instead (below). (ADR-0016)
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render authentication (and other) failures on API routes as JSON — a clean
        // 401 — rather than the login redirect this API-only backend can't satisfy.
        // The SPA's XHR calls already send Accept: application/json; this also covers
        // a bare browser navigation to a guarded route (e.g. opening a document link,
        // the reported "Route [login] not defined." symptom). (ADR-0016)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
