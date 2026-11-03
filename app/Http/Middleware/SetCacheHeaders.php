<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCacheHeaders
{
    private const PUBLIC_ROUTES = ['home', 'guest.games', 'games.search'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD'])) {
            return $response;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::PUBLIC_ROUTES, true) && ! $request->user()) {
            $response->headers->set('Cache-Control', 'public, max-age=300, s-maxage=300, stale-while-revalidate=60');
        } else {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        return $response;
    }
}
