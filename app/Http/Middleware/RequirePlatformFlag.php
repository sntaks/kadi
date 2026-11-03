<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404 unless a multi-game switch from config/platform.php is on, so routes for
 * unfinished parts of the platform don't exist yet as far as players can tell.
 */
class RequirePlatformFlag
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless((bool) config("platform.{$flag}"), 404);

        return $next($request);
    }
}
