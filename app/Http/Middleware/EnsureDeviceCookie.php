<?php

namespace App\Http\Middleware;

use App\Auth\DeviceId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every browser its device id (App\Auth\DeviceId) on its first request. The id is also put on this request,
 * so a sign-in on that very first request is recorded with it.
 */
class EnsureDeviceCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (DeviceId::from($request) === null) {
            $id = DeviceId::generate();
            $request->cookies->set(DeviceId::COOKIE, $id);
            Cookie::queue(Cookie::make(DeviceId::COOKIE, $id, DeviceId::MINUTES, httpOnly: true, sameSite: 'lax'));
        }

        return $next($request);
    }
}
