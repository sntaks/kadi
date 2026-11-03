<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\WalletBalanceFetcher;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class WalletBalance extends Component
{
    /**
     * How long a fetched balance is trusted. Kept below the 30s poll
     * interval so every poll tick sees a cold cache and refetches; other
     * services can change the wallet through KadiApi at any time.
     */
    public const BALANCE_TTL_SECONDS = 25;

    public ?float $balance = null;

    public bool $hasError = false;

    public bool $needsLoad = false;

    /**
     * Drives the echo-private:user.{userId} listener on resync() below. Defaults to 0 (never a
     * real user id) rather than null, so a guest render of this component (layouts/guest.blade.php)
     * still produces a valid, harmless channel name instead of a malformed placeholder.
     */
    public int $userId = 0;

    /** `legacy` for the old layouts, `pill` for the site shell's top bar (balance and Deposit). */
    #[Locked]
    public string $variant = 'legacy';

    public function mount(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->userId = $user->id;

        // Balance cache first, then the customer profile cache populated by HandleLogin
        $this->balance = app(WalletBalanceFetcher::class)->cached($user);
        $this->needsLoad = $this->balance === null;
    }

    public function loadBalance(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return;
        }

        // Check the caches before hitting the API
        if (($cached = app(WalletBalanceFetcher::class)->cached($user)) !== null) {
            $this->balance = $cached;
            $this->needsLoad = false;

            return;
        }

        $this->hasError = false;
        $this->doFetch($user);
    }

    public function refreshWallet(): void
    {
        Log::info('WalletBalance: Refreshing balance');
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            Log::error('WalletBalance: User not authenticated');

            return;
        }

        $this->hasError = false;

        // Bust all caches so doFetch always hits the API fresh
        Cache::forget("wallet_balance_{$user->id}");
        Cache::forget("wallet_last_checked_{$user->id}");
        Cache::forget("kadi.customer.{$user->id}");

        $this->doFetch($user);
    }

    /**
     * Background poll: serve the balance cache while it is warm, refetch
     * from KadiApi once it has expired. A failed poll keeps the last known
     * balance on screen instead of flipping the pill to "unavailable".
     */
    public function pollBalance(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->linked_id) {
            return;
        }

        $cached = Cache::get("wallet_balance_{$user->id}");

        if ($cached !== null) {
            $this->balance = (float) $cached;
            $this->hasError = false;
            $this->needsLoad = false;

            return;
        }

        $previous = $this->balance;

        $this->doFetch($user);

        if ($this->hasError && $previous !== null) {
            $this->balance = $previous;
            $this->hasError = false;
        }
    }

    private function doFetch(User $user): void
    {
        // Step 1 — user must be linked to KadiApi
        if (! $user->linked_id) {
            Log::warning("WalletBalance: user {$user->id} has no linked_id");
            $this->hasError = true;
            $this->needsLoad = false;

            return;
        }

        // Step 2 — verify kadi DB record exists (non-blocking, cached 60 min)
        $this->checkKadiDbLinkage($user);

        // Step 3 — fetch balance from KadiApi (one in-flight call per user, shared with the
        // dashboard and profile pages)
        $this->needsLoad = false;

        ['balance' => $balance, 'fresh' => $fresh] = app(WalletBalanceFetcher::class)->fetch($user);

        if ($balance === null) {
            $this->hasError = true;

            return;
        }

        $this->balance = $balance;

        if ($fresh) {
            $this->dispatch('wallet-refreshed');
        }
    }

    private function checkKadiDbLinkage(User $user): void
    {
        $cacheKey = "wallet_linked_{$user->id}";

        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            $exists = DB::connection('kadi')
                ->table('accounts')
                ->where('email', $user->email)
                ->exists();

            Cache::put($cacheKey, $exists, now()->addMinutes(60));
        } catch (\Throwable $e) {
            // Non-blocking — log and continue to balance fetch
            Log::warning("WalletBalance: kadi DB check failed for user {$user->id}: ".$e->getMessage());
        }
    }

    /**
     * Re-sync this instance's displayed balance whenever any
     * wallet-refreshed event fires (from this instance's own
     * refreshWallet(), a sibling instance's refresh, or a deposit/
     * withdraw success on the wallet page) — without re-calling
     * KadiApi::getCustomer(). Just re-read whichever cache key the
     * triggering action already wrote.
     *
     * Fixes: guest-layout dual-instance staleness (desktop vs. mobile
     * widget showing different balances after only one is refreshed).
     *
     * Also the real-time path: the wallet webhook broadcasts on this user's private channel the
     * instant it applies a new balance (already written to the same cache keys by then), so this
     * fires within the same request cycle instead of waiting for the next wire:poll tick.
     */
    #[On('wallet-refreshed')]
    #[On('echo-private:user.{userId},.wallet.updated')]
    public function resync(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $cached = Cache::get("wallet_balance_{$user->id}");

        if ($cached !== null) {
            $this->balance = (float) $cached;
            $this->hasError = false;

            return;
        }

        $profile = Cache::get("kadi.customer.{$user->id}");

        if ($profile && array_key_exists('balance', $profile)) {
            $this->balance = (float) $profile['balance'];
            $this->hasError = false;
        }
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.wallet-balance');
    }
}
