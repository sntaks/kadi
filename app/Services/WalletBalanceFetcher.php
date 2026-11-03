<?php

namespace App\Services;

use App\Facades\KadiApi;
use App\Livewire\WalletBalance;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reads and refreshes a player's KadiApi wallet balance through the shared cache keys
 * (`wallet_balance_{id}`, `kadi.customer.{id}`, `wallet_last_checked_{id}`), so the header,
 * dashboard and profile never disagree and never call KadiApi twice for the same player at once.
 */
class WalletBalanceFetcher
{
    /** The cached balance, or null when neither cache has one. */
    public function cached(User $user): ?float
    {
        $cached = Cache::get("wallet_balance_{$user->id}");

        if ($cached !== null) {
            return (float) $cached;
        }

        $profile = Cache::get("kadi.customer.{$user->id}");

        if (is_array($profile) && array_key_exists('balance', $profile)) {
            $balance = (float) $profile['balance'];
            Cache::put("wallet_balance_{$user->id}", $balance, now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));

            return $balance;
        }

        return null;
    }

    /**
     * Fetch the profile from KadiApi and write the shared caches. One call per player at a time:
     * a caller that waited for the lock serves what the holder just cached instead of calling again.
     *
     * @return array{balance: ?float, fresh: bool} `balance` is null when it could not be fetched;
     *                                             `fresh` is true when this call wrote new values.
     */
    public function fetch(User $user): array
    {
        if (! $user->linked_id) {
            return ['balance' => null, 'fresh' => false];
        }

        $lock = Cache::lock("wallet_fetch_{$user->id}", 10);
        $owner = false;

        try {
            $owner = $lock->block(5);

            if (! $owner) {
                // Timed out waiting: serve the last known profile rather than stacking another call.
                return ['balance' => $this->profileBalance($user), 'fresh' => false];
            }

            // Another request filled the cache while this one waited for the lock.
            if (($cached = Cache::get("wallet_balance_{$user->id}")) !== null) {
                return ['balance' => (float) $cached, 'fresh' => false];
            }

            $response = KadiApi::getCustomer($user->linked_id);
            $profile = $response['data'] ?? $response;
            $balance = (float) ($profile['balance'] ?? 0);

            Cache::put("kadi.customer.{$user->id}", $profile, now()->addHour());
            Cache::put("wallet_balance_{$user->id}", $balance, now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));
            Cache::put("wallet_last_checked_{$user->id}", now()->toISOString(), now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));

            return ['balance' => $balance, 'fresh' => true];
        } catch (\Throwable $e) {
            Log::warning("WalletBalanceFetcher: KadiApi fetch failed for user {$user->id}: ".$e->getMessage());

            return ['balance' => null, 'fresh' => false];
        } finally {
            if ($owner) {
                $lock->release();
            }
        }
    }

    /**
     * Ask KadiApi now, skipping the balance cache (after a deposit request or a game). Still one call
     * per player at a time through fetch()'s lock.
     *
     * @return array{balance: ?float, fresh: bool}
     */
    public function refresh(User $user): array
    {
        Cache::forget("wallet_balance_{$user->id}");

        return $this->fetch($user);
    }

    private function profileBalance(User $user): ?float
    {
        $profile = Cache::get("kadi.customer.{$user->id}");

        return is_array($profile) && array_key_exists('balance', $profile) ? (float) $profile['balance'] : null;
    }
}
