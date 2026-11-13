<?php

namespace App\Predict;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * A version number per player for the My bets cache (Phase 5 builds its cache keys with it). Bumping it after a
 * placement, or an attempt whose outcome is unknown, makes the next My bets read ask the API.
 */
final class MyBetsCache
{
    public static function version(User $user): int
    {
        return (int) Cache::get(self::key($user), 0);
    }

    public static function bump(User $user): void
    {
        Cache::forever(self::key($user), self::version($user) + 1);
    }

    private static function key(User $user): string
    {
        return "predict.my-bets.version.{$user->getKey()}";
    }
}
