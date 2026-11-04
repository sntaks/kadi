<?php

namespace App\Support;

use App\Events\WalletBalanceUpdated;
use App\Livewire\WalletBalance;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes a balance the Kadi API reported into the cache keys every wallet read path uses, only when its version is
 * newer than the last one written: the wallet webhook, a bet placement or a token redeem may arrive in any order,
 * and an older balance must never overwrite a newer one.
 *
 * Keys are those of the webhook (docs/wallet-webhook.md), keyed by the local users.id. The webhook's
 * `balance_version` and the `wallet_version` in wager and redeem answers are the same counter at the API
 * (wallets.wallet_version), so one guard key serves both.
 */
class WalletBalanceWriter
{
    private const VERSION_TTL_DAYS = 30;

    /**
     * @return bool Whether the balance was newer and written.
     */
    public function write(User $user, float $balance, int $version): bool
    {
        try {
            return (bool) Cache::lock("wallet_balance_write_{$user->id}", 5)->block(3, fn (): bool => $this->writeLocked($user, $balance, $version));
        } catch (LockTimeoutException) {
            // Another write for this player is stuck: write anyway, still version-checked, rather than drop a
            // balance the webhook has already marked as delivered.
            return $this->writeLocked($user, $balance, $version);
        }
    }

    private function writeLocked(User $user, float $balance, int $version): bool
    {
        $lastVersion = Cache::get("wallet_balance_version_{$user->id}");

        if ($lastVersion !== null && $version <= (int) $lastVersion) {
            return false;
        }

        $profile = Cache::get("kadi.customer.{$user->id}", []);
        $profile = is_array($profile) ? $profile : [];
        $profile['balance'] = $balance;
        Cache::put("kadi.customer.{$user->id}", $profile, now()->addHour());

        Cache::put("wallet_balance_{$user->id}", $balance, now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));
        Cache::put("wallet_last_checked_{$user->id}", now()->toISOString(), now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));
        Cache::put("wallet_balance_version_{$user->id}", $version, now()->addDays(self::VERSION_TTL_DAYS));

        try {
            broadcast(new WalletBalanceUpdated($user->id, $balance));
        } catch (Throwable) {
            // A Reverb outage must never fail the caller: the cache is warm, so an open tab picks the
            // balance up on its next wire:poll tick.
            Log::warning('Wallet balance: broadcast failed', ['user_id' => $user->id]);
        }

        return true;
    }

    /**
     * Write a `{balance, wallet_version}` (or `balance_version`) block from a Kadi API response, if it has one.
     *
     * @param  mixed  $block
     */
    public function writeFromResponse(User $user, $block): bool
    {
        if (! is_array($block) || ! is_numeric($block['balance'] ?? null)) {
            return false;
        }

        $version = $block['wallet_version'] ?? $block['balance_version'] ?? null;

        return is_numeric($version) && $this->write($user, (float) $block['balance'], (int) $version);
    }
}
