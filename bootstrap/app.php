<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->alias([
            'two-factor' => RequireTwoFactor::class,
            'api-token' => AuthenticateApiToken::class,
        ]);

        // Trusted proxies are read from config/trustedproxy.php at request
        // time (TRUSTED_PROXIES env). Not set here on purpose: this closure
        // runs before config loads, and a literal would override the env.
        // Trusting only the real proxy stops a client-supplied
        // X-Forwarded-For from spoofing the IP the rate limiters key on.

        $middleware->append(SecurityHeaders::class);

        // The theme cookie is written by JS on the login screen and read
        // before authentication, so it must stay plaintext on both sides.
        $middleware->encryptCookies(except: ['theme']);

        // SNS and API clients post without a session or CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
