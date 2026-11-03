<?php

namespace App\Games;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Holds one launch between POST /play/{game} and GET /play/{game}/open/{handoff}.
 *
 * Each launch gets its own random handle, so launches never overwrite each other
 * and the session (which concurrent Livewire requests rewrite) is not involved.
 * The form is encrypted in the cache, tied to the player and game, read once and
 * gone after LIFETIME seconds, a launch token's own lifetime. The handle itself
 * is only a pointer: useless to anyone but that player, and only once.
 */
class LaunchHandoff
{
    public const LIFETIME = 120;

    /** @param  array{action?: string, fields?: array<string, string>, error?: string}  $handoff */
    public function put(User $user, string $game, array $handoff): string
    {
        $handle = Str::random(40);

        Cache::put(self::key($handle), Crypt::encrypt($handoff + ['user' => $user->id, 'game' => $game]), self::LIFETIME);

        return $handle;
    }

    /** @return array{action?: string, fields?: array<string, string>, error?: string}|null */
    public function pull(User $user, string $game, string $handle): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $handle)) {
            return null;
        }

        $stored = Cache::pull(self::key($handle));

        if (! is_string($stored)) {
            return null;
        }

        try {
            $handoff = Crypt::decrypt($stored);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($handoff) || ($handoff['user'] ?? null) !== $user->id || ($handoff['game'] ?? null) !== $game) {
            return null;
        }

        unset($handoff['user'], $handoff['game']);

        return $handoff;
    }

    private static function key(string $handle): string
    {
        return 'game-handoff:'.$handle;
    }
}
