<?php

use App\Http\Middleware\SetLocaleFromRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
