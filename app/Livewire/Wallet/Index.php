<?php

namespace App\Livewire\Wallet;

use App\Facades\KadiApi;
use App\Livewire\WalletBalance;
use App\Models\User;
use App\Services\KadiApiService;
use App\Services\WithdrawResult;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Wallet | Kadi')]
class Index extends Component
{
    public string $filter = 'all';

    public array $kadiCustomer = [];

    public array $transactions = [];

    public bool $loadingTransactions = false;

    public bool $showDepositModal = false;

    public bool $showWithdrawModal = false;

    public bool $needsLoad = false;

    /** Set by loadPage() once transactions and the locked bonus have been fetched. */
    public bool $loaded = false;

    /*
     * Feature Toggles for Kadi Casino
     * Flipping any of these to true will restore Casino UI
     */
    public bool $depositWithdrawEnabled = true;

    public bool $coinsLogicEnabled = false;

    public bool $coinsWalletCardEnabled = false;

    public bool $withdrawalsTabEnabled = true;

    /*
    |--------------------------------------------------------------------
    | Wallet balance currency label
    |--------------------------------------------------------------------
    | Previously: session('currency.code', 'KES'). Set back to that
    | expression (or a computed property) to restore the KES label.
    */
    public string $walletCurrencyLabel = 'KES';

    /*
   |--------------------------------------------------------------------
   | Load Wallet / Purchase options
   |--------------------------------------------------------------------
   */
    public array $purchaseOptions = [
        ['type' => 'emoji', 'label' => 'Emojis', 'price' => 49],
        ['type' => 'gift',  'label' => 'Gifts',  'price' => 99],
        ['type' => 'coins', 'price' => 10,   'coins' => 150],
        ['type' => 'coins', 'price' => 20,   'coins' => 320],
        ['type' => 'coins', 'price' => 50,   'coins' => 850],
        ['type' => 'coins', 'price' => 100,  'coins' => 1800],
        ['type' => 'coins', 'price' => 250,  'coins' => 4750],
        ['type' => 'coins', 'price' => 500,  'coins' => 10000],
        // 'best' flags this as the strongest coins-per-shilling ratio in the
        // set (21 coins/KSH vs. 15-20 for the rest) — drives the "Best Value"
        // ribbon in the UI. Move the flag if you add/change packages later.
        ['type' => 'coins', 'price' => 1000, 'coins' => 21000, 'best' => true],
    ];

    public ?int $selectedPurchaseIndex = null;

    public bool $processingPurchase = false;

    public ?string $purchaseError = null;

    public bool $processingWithdraw = false;

    public ?string $withdrawError = null;

    public string $depositAmount = '';

    public string $withdrawAmount = '';

    public bool $processingDeposit = false;

    public ?string $depositError = null;

    public bool $confirmingDeposit = false;

    public bool $confirmingWithdraw = false;

    /** Idempotency key for the withdrawal currently being confirmed. */
    public ?string $withdrawKey = null;

    /** deposit | withdraw: the flow to reopen once the phone is confirmed. */
    public ?string $resumeAfterPhone = null;

    public bool $awaitingDeposit = false;

    public float $depositBaseline = 0;

    public int $depositPolls = 0;

    /** Poll every 5s, so this gives up after ~2 minutes. */
    public const MAX_DEPOSIT_POLLS = 24;

    public const MIN_DEPOSIT = 10;

    /** Excise duty withheld from every deposit; the rest is loaded into the wallet. */
    public const EXCISE_DUTY_RATE = 0.05;

    public const MIN_WITHDRAWAL = 50;

    public ?string $successMessage = null;

    public ?float $balance = null;

    /** Signup bonus not yet played through: KadiApi refuses to withdraw it (GET customers/{id}/promotions). */
    public float $lockedBonus = 0;

    /** Drives the echo-private:user.{userId} listener on syncCustomer() below. */
    public int $userId = 0;

    public function mount(): void
    {
        $this->userId = (int) auth()->id();
        $this->kadiCustomer = Cache::get('kadi.customer.'.auth()->id(), []);
        $this->balance = (float) ($this->kadiCustomer['balance'] ?? 0);

        // Cold cache (e.g. right after login): loadPage() fetches the profile
        // instead of silently showing 0.
        if ($this->kadiCustomer === []) {
            $this->needsLoad = true;
        }
    }

    /**
     * Everything that calls KadiApi runs here, from wire:init, so the page is sent without waiting
     * on it and shows skeletons meanwhile. Withdraw stays disabled until this has run, because the
     * withdrawable amount needs both the balance and the locked bonus.
     */
    public function loadPage(): void
    {
        if ($this->needsLoad) {
            $this->refreshCustomer();
        }

        $this->loadTransactions();
        $this->loadPromotions();
        $this->loaded = true;
    }

    /**
     * Real-time path: the wallet webhook broadcasts on this user's private channel the instant it
     * applies a new balance (already written to the kadi.customer cache by then), so this fires
     * within the same request cycle instead of waiting for the next wire:poll tick.
     */
    #[On('wallet-refreshed')]
    #[On('echo-private:user.{userId},.wallet.updated')]
    public function syncCustomer(): void
    {
        $profile = Cache::get('kadi.customer.'.auth()->id());
        if ($profile) {
            $this->kadiCustomer = $profile;
            $this->balance = (float) ($profile['balance'] ?? 0);
        }
    }

    /**
     * 30s background poll for balances changed by other services through
     * KadiApi. Skipped while a deposit/withdraw/purchase is in flight (the
     * deposit flow has its own 5s poll and baseline). Serves the balance
     * cache while warm; refetches when it has expired, behind the same lock
     * the nav widget uses so open tabs share one upstream call.
     */
    public function pollBalance(): void
    {
        if ($this->awaitingDeposit || $this->processingDeposit || $this->processingWithdraw || $this->processingPurchase) {
            return;
        }

        $user = auth()->user();

        if (! $user || ! $user->linked_id) {
            return;
        }

        if (Cache::get('wallet_balance_'.$user->id) === null) {
            $lock = Cache::lock("wallet_fetch_{$user->id}", 10);

            if ($lock->get()) {
                try {
                    $this->reloadCustomerProfile($user);
                } finally {
                    $lock->release();
                }
            }
        }

        $this->syncCustomer();
        $this->loadPromotions();
    }

    /**
     * How much signup bonus is still locked. Cached briefly (it drops as the player stakes). On any
     * error it shows nothing locked; KadiApi still refuses a withdrawal that would use it.
     */
    public function loadPromotions(): void
    {
        $user = auth()->user();

        if (! $user?->linked_id) {
            $this->lockedBonus = 0;

            return;
        }

        try {
            $promotions = Cache::remember(
                "kadi.promotions.{$user->id}",
                now()->addSeconds((int) config('kadi.promotions.cache_seconds', 60)),
                fn () => KadiApi::getPromotions((int) $user->linked_id),
            );
            $this->lockedBonus = (float) ($promotions['locked_amount'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning("Could not load promotions for user {$user->id}");
            $this->lockedBonus = 0;
        }
    }

    /** What the player can withdraw now: the balance minus locked bonus, never below 0. */
    #[Computed]
    public function withdrawableBalance(): float
    {
        return max(0.0, (float) $this->balance - $this->lockedBonus);
    }

    /** Signed up with a promo code that KadiApi did not apply; shown until dismissed. */
    #[Computed]
    public function showPromoNotice(): bool
    {
        $user = auth()->user();

        return config('kadi.promotions.enabled')
            && $user?->signup_promo_code !== null
            && $user->promo_code_applied === false
            && $user->promo_notice_dismissed_at === null;
    }

    public function dismissPromoNotice(): void
    {
        auth()->user()?->forceFill(['promo_notice_dismissed_at' => now()])->save();
        unset($this->showPromoNotice);
    }

    public function loadTransactions(): void
    {
        $customerId = auth()->user()->linked_id;

        if (! $customerId) {
            return;
        }

        try {
            $response = KadiApi::getTransactions($customerId, $this->filter);
            $this->transactions = $response['transactions'] ?? [];
        } catch (\Throwable $e) {
            $this->transactions = [];
        }
    }

    public function setFilter(string $filter): void
    {
        // Guard: Don't allow filtering by a tab that's currently disabled
        if ($filter === 'withdrawals' && ! $this->withdrawalsTabEnabled) {
            return;
        }

        $this->filter = $filter;
        $this->loadTransactions();
    }

    public function refreshCustomer(): void
    {
        $customerId = auth()->user()->linked_id;

        $this->needsLoad = false;

        if (! $customerId) {
            $this->kadiCustomer = [];

            return;
        }

        $cached = Cache::get('kadi.customer.'.auth()->id());

        if ($cached) {
            $this->kadiCustomer = $cached;
            $this->balance = (float) $cached['balance'] ?? 0;

            return;
        }

        try {
            $response = KadiApi::getCustomer($customerId);
            $data = $response['data'] ?? $response;
            Cache::put('kadi.customer.'.auth()->id(), $data, now()->addHour());
            $this->kadiCustomer = $data;
            $this->balance = (float) ($data['balance'] ?? 0);
        } catch (\Throwable $e) {
            $this->kadiCustomer = [];
        }
    }

    /*
    |--------------------------------------------------------------------
    | Load Wallet / Purchases (M-Pesa STK Push)
    |--------------------------------------------------------------------
    | initiatePurchase() is wired to each tile in the UI. It currently
    | just marks the purchase as "processing" and calls the placeholder
    | below. Wire requestMpesaStkPush() up to the real external M-Pesa
    | API when it's ready.
    */
    public function initiatePurchase(int $index): void
    {
        $this->purchaseError = null;

        $option = $this->purchaseOptions[$index] ?? null;

        if (! $option) {
            $this->purchaseError = 'Invalid purchase option';

            return;
        }

        if (! auth()->user()->hasVerifiedPhone()) {
            $this->purchaseError = 'Please confirm your phone number to complete this purchase';
            $this->dispatch('open-phone-required', purpose: 'coins');

            return;
        }

        $this->selectedPurchaseIndex = $index;
        $this->processingPurchase = true;
        $this->requestStkPush($option);
    }

    /**
     * Placeholder for the external M-Pesa STK push integration.
     *
     * Suggested implementation once the external API is available:
     *
     *   $reference = $option['type'] === 'coins'
     *       ? 'coins-'.$option['coins']
     *       : $option['type'].'-'.($option['label'] ?? '');
     *
     *   $response = KadiApi::stkPush([
     *       'phone'     => $phone,
     *       'amount'    => $option['price'],
     *       'reference' => $reference,
     *   ]);
     *
     *   // Handle response / dispatch an event so the UI can show
     *   // a "check your phone" prompt, then rely on a webhook/callback
     *   // to credit coins, emojis, or gifts once M-Pesa confirms payment.
     */
    protected function requestStkPush(array $option): void
    {
        $user = auth()->user();
        if ($option['type'] === 'coins') {
            $response = KadiApi::stkDeposit($user, $option['price']);
        } else {
            $response = KadiApi::stkLoad($user, $option);
        }
        if ($response) {
            $this->reloadCustomerProfile($user);
            $this->loadTransactions();
            $this->dispatch('wallet-refreshed');
        }
        $this->processingPurchase = is_null($response);
        $this->purchaseError = $response ? null : 'Error processing purchase';
    }

    /**
     * Re-fetch the authoritative customer profile from KadiApi and refresh
     * every balance cache. Shared by the deposit and withdraw success paths.
     */
    protected function reloadCustomerProfile(User $user): void
    {
        try {
            $response = KadiApi::getCustomer($user->linked_id);
            $profile = $response['data'] ?? $response;
            $balance = (float) ($profile['balance'] ?? 0);

            Cache::put('kadi.customer.'.$user->id, $profile, now()->addHour());
            // The top-nav WalletBalance widget reads this key first, so it
            // must be overwritten too or it keeps showing the stale balance.
            Cache::put('wallet_balance_'.$user->id, $balance, now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));
            Cache::put('wallet_last_checked_'.$user->id, now()->toISOString(), now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));

            $this->kadiCustomer = $profile;
            $this->balance = $balance;
        } catch (\Throwable $e) {
            Log::error("Error fetching customer {$user->id} profile after transaction");
        }

        // A stake, withdrawal or refusal may have changed what is locked.
        Cache::forget("kadi.promotions.{$user->id}");
        $this->loadPromotions();
    }

    /**
     * Open the deposit modal in its entry state.
     */
    public function openDeposit(): void
    {
        $this->depositError = null;
        $this->confirmingDeposit = false;
        $this->showDepositModal = true;
    }

    /**
     * Open the withdraw modal in its entry state.
     */
    public function openWithdraw(): void
    {
        $this->withdrawError = null;
        $this->confirmingWithdraw = false;
        $this->showWithdrawModal = true;
    }

    /**
     * Step 1 of the deposit flow: validate the amount, then reveal the
     * confirmation step inside the modal. Nothing hits the API yet.
     */
    public function requestDeposit(): void
    {
        $this->depositError = null;
        $this->successMessage = null;

        if (($message = $this->guardLinkedAccount()) !== null) {
            $this->depositError = $message;

            return;
        }

        if ($this->promptForPhoneIfMissing()) {
            return;
        }

        if ((float) $this->depositAmount < self::MIN_DEPOSIT) {
            $this->depositError = 'Minimum deposit amount is KES '.self::MIN_DEPOSIT.'.';

            return;
        }

        $this->confirmingDeposit = true;
    }

    /**
     * An STK push is charged to the player's phone, so it needs a phone confirmed by SMS code. When
     * it is missing or unconfirmed, close the deposit modal and open the phone modal instead.
     */
    protected function promptForPhoneIfMissing(): bool
    {
        if (auth()->user()?->hasVerifiedPhone()) {
            return false;
        }

        $this->showDepositModal = false;
        $this->confirmingDeposit = false;
        $this->resumeAfterPhone = 'deposit';
        $this->dispatch('open-phone-required', purpose: 'deposit');

        return true;
    }

    /**
     * Withdrawals are paid to the player's phone: same rule as deposits.
     */
    protected function promptForPhoneBeforeWithdraw(): bool
    {
        if (auth()->user()?->hasVerifiedPhone()) {
            return false;
        }

        $this->showWithdrawModal = false;
        $this->confirmingWithdraw = false;
        $this->withdrawKey = null;
        $this->resumeAfterPhone = 'withdraw';
        $this->dispatch('open-phone-required', purpose: 'withdraw');

        return true;
    }

    /**
     * Once the phone is confirmed, put the user back in the flow they started.
     */
    #[On('phone-saved')]
    public function resumeDeposit(): void
    {
        $resume = $this->resumeAfterPhone;
        $this->resumeAfterPhone = null;

        if ($resume === 'withdraw' && $this->withdrawAmount !== '') {
            $this->openWithdraw();

            return;
        }

        if ($this->depositAmount !== '') {
            $this->openDeposit();
        }
    }

    /**
     * Split a deposit into the excise duty withheld and the amount that
     * reaches the wallet. Uses the same rounding as the STK push amount.
     *
     * @return array{amount: int, excise: float, credited: float}
     */
    public static function depositBreakdown(string|int|float $amount): array
    {
        $amount = max(0, (int) round((float) $amount));
        $excise = round($amount * self::EXCISE_DUTY_RATE, 2);

        return [
            'amount' => $amount,
            'excise' => $excise,
            'credited' => round($amount - $excise, 2),
        ];
    }

    public function cancelDeposit(): void
    {
        $this->confirmingDeposit = false;
    }

    /**
     * Step 2 of the deposit flow (after user confirmation): send the M-Pesa
     * STK push for the free-form amount. A successful response means the
     * prompt was SENT — the balance updates only after the user completes
     * payment on their phone, so no cache refresh or event dispatch here.
     */
    public function confirmDeposit(): void
    {
        $user = auth()->user();

        if (! $user?->linked_id) {
            return;
        }

        if ($this->promptForPhoneIfMissing()) {
            return;
        }

        $this->processingDeposit = true;
        $success = KadiApi::stkDeposit($user, (int) round((float) $this->depositAmount));
        $this->processingDeposit = false;

        if (! $success) {
            $this->confirmingDeposit = false;
            $this->depositError = 'Deposit could not be initiated right now. Please try again shortly.';

            return;
        }

        $this->showDepositModal = false;
        $this->confirmingDeposit = false;
        $this->depositAmount = '';
        $this->successMessage = 'Deposit request sent. Confirm the M-Pesa prompt on your phone.';

        // The balance only changes once the user pays on their phone, so
        // poll the API (bypassing the caches) until it moves.
        $this->depositBaseline = (float) $this->balance;
        $this->depositPolls = 0;
        $this->awaitingDeposit = true;
    }

    /**
     * Polled (wire:poll) while a deposit is pending. Each tick is a hard
     * refresh: it re-fetches the profile from the API and overwrites every
     * balance cache, then tells the page and the top-nav widget to resync.
     */
    public function checkDepositStatus(): void
    {
        if (! $this->awaitingDeposit) {
            return;
        }

        $user = auth()->user();
        $this->depositPolls++;

        $this->reloadCustomerProfile($user);

        if ((float) $this->balance > $this->depositBaseline) {
            $this->awaitingDeposit = false;
            $this->loadTransactions();
            $this->dispatch('wallet-refreshed');
            $this->successMessage = 'Deposit received. Your vault balance has been updated.';

            return;
        }

        if ($this->depositPolls >= self::MAX_DEPOSIT_POLLS) {
            $this->awaitingDeposit = false;
            $this->loadTransactions();
            $this->dispatch('wallet-refreshed');
            $this->successMessage = 'We have not received your deposit yet. Your balance will update once M-Pesa confirms the payment.';
        }
    }

    /**
     * Step 1 of the withdraw flow: validate the amount, then reveal the
     * confirmation step inside the modal. The confirmation step is not
     * reachable below the minimum — the user sees an inline message
     * instead. Nothing hits the API yet.
     */
    public function requestWithdraw(): void
    {
        $this->withdrawError = null;
        $this->successMessage = null;

        if (($message = $this->guardLinkedAccount()) !== null) {
            $this->withdrawError = $message;

            return;
        }

        if ($this->promptForPhoneBeforeWithdraw()) {
            return;
        }

        if (($message = $this->validateWithdrawAmount()) !== null) {
            $this->withdrawError = $message;

            return;
        }

        $this->withdrawKey = (string) Str::uuid();
        $this->confirmingWithdraw = true;
    }

    public function cancelWithdraw(): void
    {
        $this->confirmingWithdraw = false;
        $this->withdrawKey = null;
    }

    /**
     * Step 2 of the withdraw flow (after user confirmation).
     *
     * Success flow: close modal -> re-fetch authoritative profile into cache
     * -> dispatch('wallet-refreshed') so this page and all WalletBalance
     * widget instances resync -> loadTransactions() -> success banner.
     *
     * On failure the modal returns to the amount step with an inline
     * message; the balance is never optimistically decremented.
     *
     * TODO(Phase 3 sign-off): confirm the withdrawals/{encrypted_linked_id}
     * endpoint shape against staging KadiApi before enabling publicly.
     */
    public function confirmWithdraw(): void
    {
        $user = auth()->user();

        if (! $user?->linked_id) {
            return;
        }

        if ($this->promptForPhoneBeforeWithdraw()) {
            return;
        }

        if (($message = $this->validateWithdrawAmount()) !== null) {
            $this->confirmingWithdraw = false;
            $this->withdrawError = $message;

            return;
        }

        // Re-entry guard: a double-tap must not start a second payout.
        if ($this->processingWithdraw) {
            return;
        }

        // One key per confirmed attempt; reused if this call is repeated.
        $this->withdrawKey ??= (string) Str::uuid();

        $this->processingWithdraw = true;

        try {
            $result = KadiApi::withdraw($user, (float) $this->withdrawAmount, $this->withdrawKey);
        } finally {
            $this->processingWithdraw = false;
        }

        if ($result->outcome === WithdrawResult::REJECTED) {
            // Definitively not paid out. Do not optimistically change the
            // balance; the API reverses any debit, so resync from it.
            $this->confirmingWithdraw = false;
            $this->withdrawKey = null;
            $this->withdrawError = $result->message;
            $this->reloadCustomerProfile($user);
            $this->dispatch('wallet-refreshed');

            return;
        }

        $this->showWithdrawModal = false;
        $this->confirmingWithdraw = false;
        $this->withdrawAmount = '';
        $this->withdrawKey = null;
        $this->reloadCustomerProfile($user);
        $this->loadTransactions();
        $this->dispatch('wallet-refreshed');

        $this->successMessage = $result->isUnknown()
            ? 'We are confirming your withdrawal. Check your transaction history before trying again.'
            : 'Withdrawal sent to M-Pesa'
                .($result->ledgerEntryId ? " (ref {$result->ledgerEntryId})" : '')
                .'. You will receive an M-Pesa notification shortly.';
    }

    /**
     * Shared linked-account guard; returns an error message or null when ok.
     */
    protected function guardLinkedAccount(): ?string
    {
        if (! auth()->user()?->linked_id) {
            return 'Your account is not linked to a vault. Please contact support.';
        }

        return null;
    }

    /**
     * Withdraw-specific amount rules: minimum KES 50, at most the cached
     * balance, and not the locked signup bonus. Returns an error message or
     * null when valid. KadiApi still decides (signup_bonus_locked).
     */
    protected function validateWithdrawAmount(): ?string
    {
        // Withdraw is disabled until loadPage() has run; never check against a locked bonus that
        // was not fetched yet (it would read as 0).
        if (! $this->loaded) {
            $this->loadPromotions();
        }

        $amount = (float) $this->withdrawAmount;
        $balance = (float) ($this->kadiCustomer['balance'] ?? 0);

        if ($amount < self::MIN_WITHDRAWAL) {
            return 'Minimum withdrawal amount is KES '.self::MIN_WITHDRAWAL.'.';
        }

        if ($amount > $balance) {
            return 'Insufficient balance for this withdrawal.';
        }

        if ($this->lockedBonus > 0 && $amount > $balance - $this->lockedBonus) {
            return KadiApiService::signupBonusLockedMessage([
                'locked_amount' => $this->lockedBonus,
                'available' => max(0, $balance - $this->lockedBonus),
            ]);
        }

        return null;
    }

    /**
     * Formatted M-Pesa number shown on confirmation steps.
     * Stored as 2547XXXXXXXX; displayed as +254 7XX XXX XXX.
     */
    #[Computed]
    public function mpesaPhone(): ?string
    {
        $phone = auth()->user()->phone ?? null;

        if (! $phone) {
            return null;
        }

        if (strlen($phone) === 12 && str_starts_with($phone, '254')) {
            return '+254 '.substr($phone, 3, 3).' '.substr($phone, 6, 3).' '.substr($phone, 9);
        }

        return $phone;
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.wallet.index', [
            'paybill' => config('kadi.paybill'),
        ])->layout('layouts.app');
    }
}
