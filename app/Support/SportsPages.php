<?php

namespace App\Support;

/**
 * Whether the sports pages are on (PLATFORM_SPORTS), the same condition as the /sports routes (RequirePlatformFlag
 * sports). The shell, the landing, the menu, Activity and the search all ask here, so none of them shows sports (or
 * the bet slip) while /sports answers 404.
 */
final class SportsPages
{
    public static function on(): bool
    {
        return (bool) config('platform.sports');
    }
}
