<?php

namespace App\Livewire\Sports;

use App\Account\ResponsiblePlay;
use App\Models\User;
use App\Sportsbook\Betting\BetNotPlaced;
use App\Sportsbook\Betting\BetSlipQuote;
use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Betting\BookingCodes\CreateBookingCode;
use App\Sportsbook\Betting\BookingCodes\ResolveBookingCode;
use App\Sportsbook\Betting\PlaceBet;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\OddsChangePolicy;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bet slip (plan §2, Phase 9): a column beside the sports pages on wide screens, a sheet elsewhere. Picks live in
 * the browser (the Alpine `betslip` store); this component checks them against current prices on the server every
 * time they change, and never trusts a price sent by the browser. Placing goes through PlaceBet, so the Kadi API
 * takes the stake; while its answer is unknown the slip says "Checking your bet…" and asks again.
 *
 * @property-read BetSlipQuote $quote
 * @property-read ?Bet $placedBet
 * @property-read Collection<int, FreeBet> $freeBets
 * @property-read ?FreeBet $selectedFreeBet
 * @property-read Collection<int, Bet> $openBets
 * @property-read ?float $knownBalance
 * @property-read ?string $blocked
 */
class BetSlip extends Component
{
    /** @var array<int, mixed> Picks as sent by the browser: [{fixture_id, selection_id, price}]. */
    public array $picks = [];

    public string $stake = '';

    public ?string $bookingCode = null;

    public string $codeToLoad = '';

    public string $oddsPolicy = 'higher';

    /** The free bet chosen as the stake, or '' to pay with cash. */
    public string $freeBetId = '';

    /** One per slip: submitting the same slip twice finds the bet already placed instead of placing another. */
    #[Locked]
    public string $slipKey = '';

    /** The bet just placed (or being checked), shown until the player starts a new slip. */
    #[Locked]
    public ?int $placedBetId = null;

    /** Why the last attempt was refused (a BetNotPlaced reason), so the slip can offer the right next step. */
    #[Locked]
    public ?string $refusal = null;

    /** @var list<string> Selections on a loaded booking code that can no longer be bet on. */
    public array $unavailableFromCode = [];

    public function mount(): void
    {
        $this->slipKey = (string) Str::uuid();
        $user = auth()->user();
        $this->oddsPolicy = $user instanceof User ? (OddsChangePolicy::tryFrom((string) $user->odds_change_policy) ?? OddsChangePolicy::Higher)->value : OddsChangePolicy::Higher->value;
    }

    /**
     * @param  array<int, mixed>  $picks
     */
    public function syncPicks(array $picks): void
    {
        $this->picks = array_slice(array_values($picks), 0, (int) config('sportsbook.limits.max_selections'));
        $this->bookingCode = null;
        $this->refusal = null;
        $this->resetErrorBag('bet');

        if ($this->picks !== []) {
            $this->placedBetId = null;
        }

        unset($this->quote);
    }

    /** Remembered on the account, so the next slip starts with the same choice. */
    public function updatedOddsPolicy(string $value): void
    {
        $user = auth()->user();
        $policy = OddsChangePolicy::tryFrom($value);

        if ($policy === null) {
            $this->oddsPolicy = OddsChangePolicy::Higher->value;

            return;
        }

        if ($user instanceof User) {
            $user->forceFill(['odds_change_policy' => $policy->value])->save();
        }
    }

    public function updatedFreeBetId(): void
    {
        $this->resetErrorBag('bet');
        $this->refusal = null;
        unset($this->quote, $this->selectedFreeBet);
    }

    public function placeBet(PlaceBet $placeBet): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->resetErrorBag('bet');
        $this->refusal = null;

        try {
            $bet = $placeBet->handle($user, $this->picks, trim($this->stake), OddsChangePolicy::tryFrom($this->oddsPolicy), $this->slipKey, $this->selectedFreeBet?->id, request()->ip());
        } catch (BetNotPlaced $e) {
            $this->refusal = $e->reason;
            $this->addError('bet', $e->getMessage());
            unset($this->quote, $this->freeBets, $this->selectedFreeBet);

            return;
        }

        $this->placedBetId = $bet->id;
        $this->slipKey = (string) Str::uuid();
        $this->stake = '';
        $this->freeBetId = '';
        unset($this->freeBets, $this->selectedFreeBet, $this->placedBet, $this->openBets);
        $this->dispatch('betslip-clear');
        $this->dispatch('wallet-refreshed');
        $this->trackPlaced($bet);
    }

    /** Polled while a placement is being checked with the wallet. */
    public function refreshPlaced(): void
    {
        unset($this->placedBet, $this->openBets);

        if ($this->placedBet?->status === BetStatus::Open) {
            $this->trackPlaced($this->placedBet);
            $this->dispatch('wallet-refreshed');
        }
    }

    /**
     * Analytics (resources/js/site/analytics.js), once the wallet has taken the bet: a pending one is counted when
     * the check confirms it, a rejected one never. The kind of bet only, never the stake, payout or reference.
     */
    private function trackPlaced(Bet $bet): void
    {
        if ($bet->status !== BetStatus::Open) {
            return;
        }

        $this->dispatch('kadi-track', name: 'bet_placed', params: ['product' => 'sportsbook', 'bet_type' => $bet->type->value, 'legs' => count($this->picks) ?: $bet->legs()->count(), 'free_bet' => $bet->isFreeBet()]);
    }

    public function newSlip(): void
    {
        $this->placedBetId = null;
    }

    #[Computed]
    public function placedBet(): ?Bet
    {
        return $this->placedBetId === null
            ? null
            : Bet::query()->whereKey($this->placedBetId)->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function quote(): BetSlipQuote
    {
        $freeBet = $this->selectedFreeBet;

        return $freeBet === null
            ? BetSlipQuote::for($this->picks, $this->stake, app(BettableSelections::class))
            : BetSlipQuote::for($this->picks, (string) Money::toShillings($freeBet->amount), app(BettableSelections::class), isFreeBet: true);
    }

    /**
     * @return Collection<int, FreeBet>
     */
    #[Computed]
    public function freeBets(): Collection
    {
        return auth()->check() ? FreeBet::query()->where('user_id', auth()->id())->usable()->get() : new Collection;
    }

    #[Computed]
    public function selectedFreeBet(): ?FreeBet
    {
        return $this->freeBetId === '' ? null : $this->freeBets->firstWhere('id', (int) $this->freeBetId);
    }

    /**
     * The player's open and pending bets, newest first, for the column under the slip.
     *
     * @return Collection<int, Bet>
     */
    #[Computed]
    public function openBets(): Collection
    {
        return auth()->check()
            ? Bet::query()->where('user_id', auth()->id())->whereIn('status', [BetStatus::Open, BetStatus::Pending])->with('legs')->latest('placed_at')->limit(3)->get()
            : new Collection;
    }

    /**
     * The balance the site last saw (the wallet pill's), for the "more than your wallet holds" hint. Only a hint: it
     * never stops the bet, because a deposit may not show here yet. The wallet decides when the bet is placed.
     */
    #[Computed]
    public function knownBalance(): ?float
    {
        $balance = auth()->check() ? Cache::get('wallet_balance_'.auth()->id()) : null;

        return is_numeric($balance) ? (float) $balance : null;
    }

    /**
     * Why this player cannot bet right now, from what the site already knows (the API checks again on placing).
     */
    #[Computed]
    public function blocked(): ?string
    {
        $user = auth()->user();

        return match (true) {
            ! $user instanceof User => null,
            ! config('platform.sports_betting') => 'Betting opens soon. You can build a slip and share it in the meantime.',
            ! $user->hasVerifiedEmail() => 'Verify your e-mail address to place bets.',
            ! $user->linked_id => 'Your account is still being set up. Try again in a minute.',
            app(ResponsiblePlay::class)->onBreak($user, live: false) => 'Betting is paused while your break from play runs.',
            default => null,
        };
    }

    public function acceptPriceChanges(): void
    {
        $this->dispatch('betslip-accept-prices', prices: $this->quote->changedPrices());
    }

    public function removeUnavailable(): void
    {
        $ids = collect($this->quote->lines)
            ->filter(fn (array $line): bool => $line['status'] === 'unavailable')
            ->map(fn (array $line): int => $line['selection']->id)
            ->values()
            ->all();

        $this->dispatch('betslip-remove', selectionIds: $ids);
    }

    public function createBookingCode(CreateBookingCode $create): void
    {
        $this->resetErrorBag();

        if (! $this->withinLimit('booking-code-create', 10)) {
            $this->addError('booking', 'Too many booking codes. Try again in a minute.');

            return;
        }

        try {
            $user = auth()->user();
            $selectionIds = collect($this->quote->lines)->map(fn (array $line): int => $line['selection']->id)->all();
            $this->bookingCode = $create->handle($selectionIds, $user instanceof User ? $user : null)->code;
        } catch (InvalidArgumentException $exception) {
            $this->addError('booking', $exception->getMessage());
        }
    }

    #[On('load-booking-code')]
    public function loadBookingCode(?string $code = null): void
    {
        $this->resetErrorBag();
        $code = trim($code ?? $this->codeToLoad);

        if ($code === '') {
            return;
        }

        if (! $this->withinLimit('booking-code-load', 30)) {
            $this->addError('loadCode', 'Too many attempts. Try again in a minute.');

            return;
        }

        try {
            $resolved = app(ResolveBookingCode::class)->handle($code);
        } catch (InvalidArgumentException $exception) {
            $this->addError('loadCode', $exception->getMessage());

            return;
        }

        $this->codeToLoad = '';
        $this->unavailableFromCode = $resolved['unavailable'];
        $this->dispatch('betslip-replace', picks: $resolved['picks']);
    }

    public function render(): View
    {
        return view('livewire.sports.bet-slip');
    }

    /**
     * Booking-code limits per player when signed in, so players sharing a carrier's address (CGNAT is the norm on
     * Kenyan mobile data) never use up each other's tries; guests share a looser limit per address.
     */
    private function withinLimit(string $action, int $perMinute): bool
    {
        $key = $action.':'.(auth()->check() ? 'user:'.auth()->id() : 'ip:'.request()->ip());
        $perMinute = auth()->check() ? $perMinute : $perMinute * 3;

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
