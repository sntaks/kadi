<?php

namespace App\Livewire\Referrals;

use App\Facades\KadiApi;
use App\Referrals\ReferralCodes;
use App\Services\ReferralWithdrawResult;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Invite & Earn": the player's code, link and QR code, their referral stats and list, the referral
 * wallet and its withdrawals. KadiApi owns all of it; this page only reads it and sends withdrawals.
 *
 * Everything loads after the first paint (wire:init), each block on its own, so one slow call does
 * not blank the page. No polling: final withdrawal statuses show on the next visit or Refresh.
 */
#[Title('Invite & Earn | Kadi')]
class Index extends Component
{
    // State loaded from KadiApi and the withdraw flow is #[Locked]: a player must not be able to
    // rewrite their balance, minimum or idempotency key from the browser.

    public const STATUSES = [
        'pending_verification' => 'Pending verification',
        'verified' => 'Verified',
        'deposited' => 'Deposited',
    ];

    public const WITHDRAWAL_STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];

    #[Locked]
    public bool $loaded = false;

    /** @var array{code: string, link: string, qr_code: string}|null */
    #[Locked]
    public ?array $share = null;

    #[Locked]
    public ?array $stats = null;

    #[Locked]
    public bool $statsFailed = false;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $referrals = [];

    #[Locked]
    public array $referralsMeta = [];

    #[Locked]
    public bool $referralsFailed = false;

    #[Locked]
    public string $status = '';

    #[Locked]
    public int $page = 1;

    #[Locked]
    public ?array $wallet = null;

    #[Locked]
    public bool $walletFailed = false;

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $withdrawals = [];

    #[Locked]
    public bool $withdrawalsFailed = false;

    public string $withdrawAmount = '';

    #[Locked]
    public bool $confirmingWithdraw = false;

    #[Locked]
    public bool $processingWithdraw = false;

    /** One Idempotency-Key per attempt. */
    #[Locked]
    public ?string $withdrawKey = null;

    /** Key and amount of an attempt whose outcome is unknown: retrying the same amount replays it. */
    #[Locked]
    public ?string $pendingKey = null;

    #[Locked]
    public ?int $pendingAmount = null;

    public ?string $withdrawError = null;

    public ?string $successMessage = null;

    public ?string $noticeMessage = null;

    public function mount(): void
    {
        abort_unless(config('kadi.referrals.enabled'), 404);
    }

    public function load(ReferralCodes $codes): void
    {
        $user = auth()->user();

        if (! $user->linked_id) {
            $this->loaded = true;

            return;
        }

        $this->share = $codes->ensure($user);
        $this->loadStats();
        $this->loadReferrals();
        $this->loadWallet();
        $this->loadWithdrawals();
        $this->loaded = true;
    }

    /** Bypasses the short cache, limited per player to spare KadiApi. */
    public function refresh(ReferralCodes $codes): void
    {
        if (RateLimiter::attempt('referrals-refresh:'.auth()->id(), 6, fn () => true, 60)) {
            $this->forgetCache();
        }

        $this->load($codes);
    }

    public function setStatus(string $status): void
    {
        $this->status = array_key_exists($status, self::STATUSES) ? $status : '';
        $this->page = 1;
        $this->loadReferrals();
    }

    public function goToPage(int $page): void
    {
        $last = (int) ($this->referralsMeta['last_page'] ?? 1);
        $this->page = max(1, min($page, max(1, $last)));
        $this->loadReferrals();
    }

    /** The player confirmed their phone in the modal: carry on to the confirmation step. */
    #[On('phone-verified')]
    public function resumeWithdraw(): void
    {
        if ($this->withdrawAmount !== '') {
            $this->requestWithdraw();
        }
    }

    /**
     * Step 1: check the amount, then show the confirmation. Nothing is sent yet.
     */
    public function requestWithdraw(): void
    {
        $this->withdrawError = null;
        $this->successMessage = null;
        $this->noticeMessage = null;

        if (($message = $this->withdrawBlocker()) !== null) {
            $this->withdrawError = $message;

            return;
        }

        $amount = (int) $this->withdrawAmount;

        // Same amount as an attempt whose outcome is unknown: reuse its key so KadiApi replays it.
        $this->withdrawKey = $this->pendingKey !== null && $this->pendingAmount === $amount
            ? $this->pendingKey
            : (string) Str::uuid();

        $this->confirmingWithdraw = true;
    }

    public function cancelWithdraw(): void
    {
        $this->confirmingWithdraw = false;
        $this->withdrawKey = null;
    }

    /**
     * Step 2: send the withdrawal. KadiApi pays it to the phone it holds, from the referral shortcode.
     */
    public function confirmWithdraw(): void
    {
        // Re-entry guard: a double tap must not start a second payout.
        if ($this->processingWithdraw || ! $this->confirmingWithdraw) {
            return;
        }

        if (($message = $this->withdrawBlocker()) !== null) {
            $this->confirmingWithdraw = false;
            $this->withdrawError = $message;

            return;
        }

        $user = auth()->user();
        $amount = (int) $this->withdrawAmount;
        $this->withdrawKey ??= (string) Str::uuid();
        $this->processingWithdraw = true;

        try {
            $result = KadiApi::withdrawReferral($user, $amount, $this->withdrawKey);
        } finally {
            $this->processingWithdraw = false;
        }

        $this->confirmingWithdraw = false;

        if ($result->isFinal()) {
            $this->withdrawKey = null;
            $this->pendingKey = null;
            $this->pendingAmount = null;
        } else {
            $this->pendingKey = $this->withdrawKey;
            $this->pendingAmount = $amount;
            $this->withdrawKey = null;
        }

        if ($result->outcome === ReferralWithdrawResult::PROCESSING) {
            $this->successMessage = $result->message;
            $this->withdrawAmount = '';
        } elseif ($result->outcome === ReferralWithdrawResult::PENDING) {
            $this->noticeMessage = $result->message;
        } else {
            $this->withdrawError = $result->message;
        }

        // Never change the balance ourselves: re-read it (KadiApi debits or restores it).
        $this->forgetCache();
        $this->loadStats();
        $this->loadWallet();
        $this->loadWithdrawals();
    }

    /**
     * Why a withdrawal cannot go ahead, or null. Opens the phone modal when the phone is unconfirmed.
     */
    protected function withdrawBlocker(): ?string
    {
        $user = auth()->user();

        if (! $user->linked_id) {
            return __('Your account is still being set up. Please try again shortly.');
        }

        if (! $user->hasVerifiedPhone()) {
            $this->confirmingWithdraw = false;
            $this->dispatch('open-phone-required', purpose: 'referral');

            return __('Please confirm your phone number first. Payouts go to this number.');
        }

        if ($this->wallet === null) {
            $this->loadWallet();
        }

        if ($this->wallet === null) {
            return __('We could not load your referral wallet. Please try again shortly.');
        }

        if (! ($this->wallet['withdrawable'] ?? false)) {
            return __('Referral withdrawals are not available right now.');
        }

        $minimum = $this->minimumWithdrawal();
        $amount = trim($this->withdrawAmount);

        if (! preg_match('/^\d{1,7}$/', $amount)) {
            return __('Enter a whole number of shillings.');
        }

        if ((int) $amount < $minimum) {
            return __('The minimum referral withdrawal is KES :min.', ['min' => $minimum]);
        }

        if ((int) $amount > $this->withdrawableBalance()) {
            return __('Insufficient referral balance for this withdrawal.');
        }

        return null;
    }

    public function minimumWithdrawal(): int
    {
        return (int) ($this->wallet['minimum_withdrawal'] ?? config('kadi.referrals.minimum_withdrawal', 50));
    }

    /** Whole shillings only: KES 75.50 can be withdrawn as 75. */
    public function withdrawableBalance(): int
    {
        return (int) floor((float) ($this->wallet['balance'] ?? 0));
    }

    protected function loadStats(): void
    {
        $user = auth()->user();

        try {
            $this->stats = Cache::remember($this->cacheKey('stats'), $this->cacheTtl(), fn () => KadiApi::getReferralStats((int) $user->linked_id));
            $this->statsFailed = false;
        } catch (\Throwable $e) {
            Log::warning("Referral stats for user {$user->id}: ".class_basename($e));
            $this->statsFailed = true;
        }
    }

    protected function loadReferrals(): void
    {
        $user = auth()->user();

        try {
            $body = KadiApi::getReferrals((int) $user->linked_id, $this->page, $this->status !== '' ? $this->status : null);
            $this->referrals = array_values(array_filter((array) ($body['data'] ?? []), 'is_array'));
            $this->referralsMeta = is_array($body['meta'] ?? null) ? $body['meta'] : [];
            $this->referralsFailed = false;
        } catch (\Throwable $e) {
            Log::warning("Referral list for user {$user->id}: ".class_basename($e));
            $this->referrals = [];
            $this->referralsFailed = true;
        }
    }

    protected function loadWallet(): void
    {
        $user = auth()->user();

        try {
            $body = Cache::remember($this->cacheKey('wallet'), $this->cacheTtl(), fn () => KadiApi::getReferralWallet((int) $user->linked_id));
            $this->wallet = is_array($body['data'] ?? null) ? $body['data'] : null;
            $this->walletFailed = $this->wallet === null;
        } catch (\Throwable $e) {
            Log::warning("Referral wallet for user {$user->id}: ".class_basename($e));
            $this->wallet = null;
            $this->walletFailed = true;
        }
    }

    protected function loadWithdrawals(): void
    {
        $user = auth()->user();

        try {
            $body = Cache::remember($this->cacheKey('withdrawals'), $this->cacheTtl(), fn () => KadiApi::getReferralWithdrawals((int) $user->linked_id));
            $this->withdrawals = array_values(array_filter((array) ($body['data'] ?? []), 'is_array'));
            $this->withdrawalsFailed = false;
        } catch (\Throwable $e) {
            Log::warning("Referral withdrawals for user {$user->id}: ".class_basename($e));
            $this->withdrawals = [];
            $this->withdrawalsFailed = true;
        }
    }

    protected function forgetCache(): void
    {
        foreach (['stats', 'wallet', 'withdrawals'] as $part) {
            Cache::forget($this->cacheKey($part));
        }
    }

    protected function cacheKey(string $part): string
    {
        return "referral.page.{$part}.".auth()->id();
    }

    protected function cacheTtl(): int
    {
        return (int) config('kadi.referrals.page_cache_seconds', 60);
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.account.referrals', [
            'statuses' => self::STATUSES,
            'withdrawalStatuses' => self::WITHDRAWAL_STATUSES,
        ])->layout('layouts.site', ['shell' => 'app']);
    }
}
