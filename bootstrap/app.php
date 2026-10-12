<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Okta posts the SAML assertion here from its own origin, so there is
        // no session and no token to present. Excluding the route is correct
        // rather than a shortcut: what protects this endpoint is the signature
        // on the assertion, its intended recipient, its validity window and
        // its one-time id, all of which are checked before anyone is signed in.
        $middleware->validateCsrfTokens(except: [
            'auth/saml/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
