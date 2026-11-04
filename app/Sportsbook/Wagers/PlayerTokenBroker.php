<?php

namespace App\Sportsbook\Wagers;

use App\Models\User;
use App\Services\KadiApiService;
use App\Support\WalletBalanceWriter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A sportsbook player token for the signed-in player, obtained without leaving the site (plan §5.3):
 *
 * 1. site key: POST customers/{id}/launch-tokens {game: sportsbook}
 * 2. game key: POST launch-tokens/redeem {token}
 * 3. the 12-hour player token is kept encrypted in the cache, per player.
 *
 * Neither call is ever retried (a launch token is single-use, and a timed-out redeem may have spent it). A token the
 * API stops accepting (401 invalid_player_token) is forgotten and a new one obtained once. Logging out forgets it
 * here and revokes it at the API (RevokeGameSessions). Tokens are never logged, put in a URL or stored elsewhere.
 */
class PlayerTokenBroker
{
    /** Renew this long before the API's expiry, so a token never runs out in the middle of a bet. */
    private const EARLY_RENEW_SECONDS = 300;

    private const MAX_TTL_SECONDS = 12 * 3600;

    public function __construct(
        private KadiApiService $api,
        private KadiWagerClient $client,
        private WalletBalanceWriter $balances,
    ) {}

    /**
     * @throws PlayerTokenUnavailable
     */
    public function token(User $user, ?string $ip = null): string
    {
        $cached = Cache::get($this->key($user));

        if (is_string($cached)) {
            try {
                return Crypt::decryptString($cached);
            } catch (DecryptException) {
                Cache::forget($this->key($user));
            }
        }

        return $this->obtain($user, $ip);
    }

    /**
     * Forget the cached token and obtain another, after the API refused the old one.
     *
     * @throws PlayerTokenUnavailable
     */
    public function renew(User $user, ?string $ip = null): string
    {
        $this->forget($user);

        return $this->obtain($user, $ip);
    }

    public function forget(User $user): void
    {
        Cache::forget($this->key($user));
    }

    /**
     * @throws PlayerTokenUnavailable
     */
    private function obtain(User $user, ?string $ip): string
    {
        if (! $user->linked_id) {
            throw new PlayerTokenUnavailable('not_linked');
        }

        try {
            $launch = $this->api->issueLaunchToken((int) $user->linked_id, (string) config('sportsbook.wagers.game'), $ip);
        } catch (RequestException $e) {
            $code = $e->response->json('code');
            $code = is_string($code) ? $code : 'launch_refused';
            Log::warning('Sportsbook: no launch token for the player', ['user_id' => $user->id, 'status' => $e->response->status(), 'code' => $code]);

            throw new PlayerTokenUnavailable($code, $e);
        } catch (Throwable $e) {
            Log::warning('Sportsbook: no launch token for the player', ['user_id' => $user->id, 'error' => class_basename($e)]);

            throw new PlayerTokenUnavailable('launch_unavailable', $e);
        }

        $redeemed = $this->client->redeem($launch['token']);
        $token = $redeemed->data['player_token'] ?? null;

        if (! $redeemed->ok() || ! is_string($token) || $token === '') {
            Log::warning('Sportsbook: the launch token was not redeemed', ['user_id' => $user->id, 'status' => $redeemed->status, 'code' => $redeemed->code]);

            throw new PlayerTokenUnavailable($redeemed->code ?? 'redeem_failed');
        }

        Cache::put($this->key($user), Crypt::encryptString($token), $this->ttl($redeemed->data['expires_at'] ?? null));
        $this->balances->writeFromResponse($user, $redeemed->data['balance'] ?? null);

        return $token;
    }

    private function ttl(mixed $expiresAt): int
    {
        $seconds = self::MAX_TTL_SECONDS;

        if (is_string($expiresAt)) {
            try {
                $seconds = (int) CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($expiresAt), false);
            } catch (Throwable) {
                $seconds = self::MAX_TTL_SECONDS;
            }
        }

        return max(30, min(self::MAX_TTL_SECONDS, $seconds) - self::EARLY_RENEW_SECONDS);
    }

    /**
     * One token per player, shared by their sessions: a new session never spends another launch token (the site key
     * has 30 a minute for every game), and logging out anywhere revokes every API session of the player anyway.
     */
    private function key(User $user): string
    {
        return 'sportsbook.player-token.'.$user->id;
    }
}
