<?php

namespace App\Predict;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * The one prediction bet a player has sent whose outcome is not known yet (no answer, a 5xx or a 429). Kept so a
 * reload still shows "We're checking your bet" and "Try again" sends the same reference: the API never takes the
 * money twice for one reference. Encrypted in the cache for 24 hours and cleared on any definite answer. Not a
 * ledger, and never logged.
 */
final class PendingAttempt
{
    private const TTL_SECONDS = 86400;

    /**
     * @param  array{reference: string, market_id: int, side: string, stake: float, quote_id: ?string}  $attempt
     */
    public static function put(User $user, array $attempt): void
    {
        Cache::put(self::key($user), Crypt::encryptString((string) json_encode($attempt)), self::TTL_SECONDS);
    }

    /**
     * @return array{reference: string, market_id: int, side: string, stake: float, quote_id: ?string}|null
     */
    public static function get(User $user): ?array
    {
        $stored = Cache::get(self::key($user));

        if (! is_string($stored)) {
            return null;
        }

        try {
            $attempt = json_decode(Crypt::decryptString($stored), true);
        } catch (DecryptException) {
            self::forget($user);

            return null;
        }

        if (! is_array($attempt) || ! is_string($attempt['reference'] ?? null) || ! is_numeric($attempt['market_id'] ?? null)
            || ! in_array($attempt['side'] ?? null, ['yes', 'no'], true) || ! is_numeric($attempt['stake'] ?? null)) {
            return null;
        }

        return [
            'reference' => $attempt['reference'],
            'market_id' => (int) $attempt['market_id'],
            'side' => $attempt['side'],
            'stake' => (float) $attempt['stake'],
            'quote_id' => is_string($attempt['quote_id'] ?? null) ? $attempt['quote_id'] : null,
        ];
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    private static function key(User $user): string
    {
        return "predict.pending-attempt.{$user->getKey()}";
    }
}
