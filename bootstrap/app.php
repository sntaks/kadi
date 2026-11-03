<?php

use App\Http\Middleware\AuthenticatePushApiKey;
use App\Http\Middleware\CapturePromoCode;
use App\Http\Middleware\CaptureReferralCode;
use App\Http\Middleware\DetectCurrency;
use App\Http\Middleware\EnsureConsentGiven;
use App\Http\Middleware\EnsureNameIsValid;
use App\Http\Middleware\LogoutInactiveUsers;
use App\Http\Middleware\RedirectLegacyDomain;
use App\Http\Middleware\SetCacheHeaders;
use App\Http\Middleware\VerifyKadiWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            RedirectLegacyDomain::class,
        ]);

        $middleware->web(append: [
            DetectCurrency::class,
            SetCacheHeaders::class,
            LogoutInactiveUsers::class,
            EnsureConsentGiven::class,
            EnsureNameIsValid::class,
            CaptureReferralCode::class,
            CapturePromoCode::class,
        ]);

        // Set by the preloader's JavaScript (value "1", nothing sensitive). Encrypted cookies it
        // cannot decrypt are dropped, which made the preloader show on every page load.
        $middleware->encryptCookies(except: ['kadi_preloaded']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'push.api' => AuthenticatePushApiKey::class,
            'kadi.webhook' => VerifyKadiWebhookSignature::class,
        ]);

        // Laravel sorts route middleware by a priority list, and ThrottleRequests is on it, so an
        // unlisted middleware would run AFTER the throttle. The push API's rate limit is keyed by the
        // API key, which this middleware establishes, so it must run first. Otherwise the limit
        // silently falls back to the caller's IP and junk requests eat the real server's bucket.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: AuthenticatePushApiKey::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Server-to-server clients under /api always get JSON errors (404, 405, 500...), never
        // an HTML page or a redirect, even if they forget the Accept header.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
