<?php

namespace App\Support;

/**
 * A short, human description of a browser ("Chrome on Android") for sign-in alerts and the activity log. Coarse on
 * purpose: enough for a player to recognise their own device, never a fingerprint.
 */
class DeviceName
{
    /** Checked in order: Edge, Opera and Samsung Internet also say "Chrome", Chrome also says "Safari". */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const SYSTEMS = [
        'Android' => 'Android',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Windows' => 'Windows',
        'Mac OS X' => 'Mac',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    public static function from(?string $userAgent): string
    {
        $userAgent ??= '';
        $browser = self::first(self::BROWSERS, $userAgent);
        $system = self::first(self::SYSTEMS, $userAgent);

        return match (true) {
            $browser !== null && $system !== null => "{$browser} on {$system}",
            $browser !== null => $browser,
            $system !== null => "a browser on {$system}",
            default => 'an unknown device',
        };
    }

    /** @param  array<string, string>  $needles */
    private static function first(array $needles, string $userAgent): ?string
    {
        foreach ($needles as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
