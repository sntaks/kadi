<?php

namespace App\Auth;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;

/**
 * A random id per browser, kept in a long-lived cookie apart from the session cookie, so the one-device rule
 * (App\Auth\SingleSession) can tell the same device signing in again (its browser was killed during a phone call and
 * lost the session cookie) from a different one. It identifies a browser, never a person: no IP or fingerprint.
 */
class DeviceId
{
    public const COOKIE = 'kadi_device';

    /** One year, in minutes: it must outlive any session by far. */
    public const MINUTES = 525_600;

    public static function from(Request $request): ?string
    {
        $value = $request->cookies->get(self::COOKIE);

        return is_string($value) && self::valid($value) ? $value : null;
    }

    public static function generate(): string
    {
        return Str::random(40);
    }

    public static function valid(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9]{40}$/', $value) === 1;
    }
}
