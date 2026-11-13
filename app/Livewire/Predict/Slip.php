<?php

namespace App\Livewire\Predict;

use App\Account\ResponsiblePlay;
use App\Models\User;
use App\Predict\Data\Market;
use App\Predict\Data\Quote;
use App\Predict\Format;
use App\Predict\Markets;
use App\Predict\PendingAttempt;
use App\Predict\PlaceBet;
use App\Predict\Placement;
use App\Predict\PredictApi;
use App\Predict\Refusal;
use App\Predict\RefusalMessage;
use App\Predict\RequestQuote;
use App\Support\PredictPages;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The prediction bet slip (plan Phase 4), on the market page and in the market sheet. One market, one side, one
 * stake. Fixed-odds markets (reference, LMSR) bet through a 10-second quote; pool markets bet directly and their
 * figures stay estimates until the market closes.
 *
 * Everything that matters is checked here again on every action, never trusted from the browser: who may bet,
 * whether the market takes bets now, the stake limits, the quote (its own figures, held in locked properties, and
 * its expiry on our clock). Placing goes through App\Predict\PlaceBet only.
 *
 * @property-read ?Market $market
 * @property-read ?string $blocked
 */
class Slip extends Component
{
    public const QUICK_STAKES = [20, 50, 100, 500];

    #[Locked]
    public int $marketId;

    /** yes | no | '' (none picked yet). Player input: checked on every use. */
    public string $side = '';

    /** Player input, as typed. */
    public string $stake = '';

    /** @var array{multiplier: float, payout: float}|null The last estimate the API gave for this side and stake. */
    #[Locked]
    public ?array $estimate = null;

    /** @var array{id: string, market_id: int, side: string, stake: float, multiplier: float, payout: float, expires_at: string}|null */
    #[Locked]
    public ?array $quote = null;

    /** A pool market said `quote_not_needed`: bet without one. */
    #[Locked]
    public bool $poolFlow = false;

    /** pick | checking | placed */
    #[Locked]
    public string $state = 'pick';

    /** @var array{code: string, message: string, action: ?string, amount: ?float, retry_at: ?int}|null */
    #[Locked]
    public ?array $notice = null;

    /** @var array{multiplier: ?float, quote: ?array<string, mixed>}|null The price moved: what it is now. */
    #[Locked]
    public ?array $changed = null;

    /** @var array{multiplier: ?float, payout: ?float, estimate: bool, side: string, stake: float}|null */
    #[Locked]
    public ?array $placed = null;

    /** Show the "Predict" label and the question above the sides (the market page; the sheet has its own header). */
    #[Locked]
    public bool $showQuestion = false;

    /**
     * @param  ?string  $initialSide  the side picked on the card or the lobby link (not named `side`: Livewire would
     *                                assign it to the property as given)
     */
    public function mount(int $marketId, ?string $initialSide = null, bool $showQuestion = false): void
    {
        $this->marketId = $marketId;
        $this->showQuestion = $showQuestion;
        $this->side = in_array($initialSide, Market::SIDES, true) ? $initialSide : '';

        // A bet sent earlier whose outcome is still unknown: show it, so "Try again" sends the same reference.
        $user = auth()->user();
        $pending = $user instanceof User ? PendingAttempt::get($user) : null;

        if ($pending !== null && $pending['market_id'] === $marketId) {
            $this->state = 'checking';
            $this->side = $pending['side'];
            $this->stake = self::amount($pending['stake']);
        }
    }

    #[Computed]
    public function market(): ?Market
    {
        return app(Markets::class)->find($this->marketId);
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
            ! PredictPages::bettingFor($user) => PredictPages::bettingOn()
                ? __('Betting is not available on your account right now.')
                : __('Betting on predictions opens soon.'),
            ! $user->hasVerifiedEmail() => __('Verify your e-mail address to place bets.'),
            blank($user->linked_id) => __('Your account is still being set up. Try again in a minute.'),
            app(ResponsiblePlay::class)->onBreak($user, live: false) => __('Betting is paused while your break from play runs.'),
            default => null,
        };
    }

    public function pick(string $side): void
    {
        if (! in_array($side, Market::SIDES, true) || $this->state === 'checking') {
            return;
        }

        $this->side = $side;
        $this->reset('quote', 'notice', 'changed', 'placed', 'estimate');
        $this->state = 'pick';
        $this->refreshEstimate();
    }

    public function useStake(float $amount): void
    {
        if ($this->state === 'checking') {
            return;
        }

        $this->stake = self::amount($amount);
        $this->updatedStake();
    }

    public function updatedStake(): void
    {
        if ($this->state === 'checking') {
            // The attempt being checked keeps its stake: "Try again" must send the same one.
            $pending = $this->pendingHere();
            $this->stake = $pending !== null ? self::amount($pending['stake']) : $this->stake;

            return;
        }

        $this->reset('quote', 'notice', 'changed', 'placed');
        $this->state = 'pick';
        $this->refreshEstimate();
    }

    /**
     * Fixed-odds markets: ask the API for a price held 10 seconds for this side and stake.
     */
    public function getQuote(RequestQuote $requestQuote): void
    {
        $this->reset('notice', 'changed');
        [$user, $market, $stake] = $this->ready();

        if ($user === null || ! $market->usesQuote() || $this->poolFlow) {
            return;
        }

        if (! $this->withinLimit("predict-limit:quote:{$user->getKey()}", (int) config('predict.limits.quotes_per_player', 12))) {
            $this->say('rate_limited', __('Too many prices asked for. Try again in a minute.'));

            return;
        }

        $result = $requestQuote->handle($user, $market, $this->side, $stake);

        if ($result instanceof Quote) {
            $this->quote = self::quoteArray($result);

            return;
        }

        if ($result->code === 'quote_not_needed') {
            $this->poolFlow = true;

            return;
        }

        $this->refuse($result);
    }

    /**
     * Place the bet: with the open quote on fixed-odds markets, directly on pool markets. A new reference each time:
     * this is a new attempt.
     */
    public function place(PlaceBet $placeBet): void
    {
        $this->reset('notice');
        [$user, $market, $stake] = $this->ready();

        if ($user === null || $this->state === 'checking') {
            return;
        }

        // One unconfirmed bet at a time: a new attempt would replace the saved one, and its reference with it.
        $pending = PendingAttempt::get($user);

        if ($pending !== null && $pending['market_id'] !== $market->id) {
            $this->say('pending_elsewhere', __('We\'re still checking a bet you placed on another market. Open that market to try it again first.'), Refusal::RETRY, amount: (float) $pending['market_id']);

            return;
        }

        $quoteId = null;

        if ($market->usesQuote() && ! $this->poolFlow) {
            $quote = $this->openQuote($market, $stake);

            if ($quote === null) {
                return;
            }

            $quoteId = $quote['id'];
        }

        $this->send($placeBet, $user, $market, $stake, (string) Str::uuid(), $quoteId, retrying: false);
    }

    /**
     * "Try again" after an unknown outcome: the same reference, market, side, stake and quote, from the saved attempt.
     */
    public function retry(PlaceBet $placeBet): void
    {
        $user = auth()->user();
        $pending = $this->pendingHere();
        $market = $this->market;

        if (! $user instanceof User || $pending === null || $market === null) {
            $this->state = 'pick';

            return;
        }

        if (! $this->withinLimit("predict-limit:bet:{$user->getKey()}", (int) config('predict.limits.bets_per_player', 10))) {
            $this->say('rate_limited', __('Too many tries. Try again in a minute.'), Refusal::RETRY, retryAfter: 60);

            return;
        }

        $this->send($placeBet, $user, $market, $pending['stake'], $pending['reference'], $pending['quote_id'], retrying: true);
    }

    /** The price moved: take the new one (a new quote, so a new attempt with a new reference). */
    public function acceptChange(PlaceBet $placeBet): void
    {
        $quote = is_array($this->changed['quote'] ?? null) ? $this->changed['quote'] : null;
        $this->changed = null;

        if ($quote === null) {
            $this->getQuote(app(RequestQuote::class));

            return;
        }

        $this->quote = $quote;
        $this->place($placeBet);
    }

    public function another(): void
    {
        $this->reset('quote', 'notice', 'changed', 'placed', 'estimate', 'stake');
        $this->state = 'pick';
    }

    /** The feed says the market stopped taking bets: drop the open quote at once. */
    public function marketChanged(): void
    {
        unset($this->market);

        if ($this->market?->takesBets(Date::now()) !== true && $this->state !== 'checking') {
            $this->quote = null;
        }
    }

    public function render(): View
    {
        $market = $this->market;
        $now = Date::now();
        $quote = $this->quote !== null ? CarbonImmutable::parse($this->quote['expires_at']) : null;

        return view('livewire.predict.slip', [
            'market' => $market,
            'now' => $now,
            'blocked' => $this->blocked,
            'canBet' => $market !== null && $this->blocked === null && auth()->check(),
            'quoteSeconds' => $quote !== null ? max(0, (int) floor((float) $quote->format('U.u') - (float) $now->format('U.u'))) : 0,
            'closesIn' => $market?->bettingClosesAt !== null ? max(0, $market->bettingClosesAt->getTimestamp() - $now->getTimestamp()) : null,
            'quickStakes' => $market !== null ? self::quickStakes($market) : [],
            'retryIn' => isset($this->notice['retry_at']) ? max(0, (int) $this->notice['retry_at'] - $now->getTimestamp()) : 0,
        ]);
    }

    private function send(PlaceBet $placeBet, User $user, Market $market, float $stake, string $reference, ?string $quoteId, bool $retrying): void
    {
        if (! $retrying && ! $this->withinLimit("predict-limit:bet:{$user->getKey()}", (int) config('predict.limits.bets_per_player', 10))) {
            $this->say('rate_limited', __('Too many bets in a minute. Try again shortly.'));

            return;
        }

        $result = $placeBet->handle($user, $market, $this->side, $stake, $reference, $quoteId, $retrying);

        match ($result->outcome) {
            Placement::PLACED => $this->wasPlaced($market, $stake, $result),
            Placement::UNKNOWN => $this->checking($result->retryAfter),
            Placement::CHANGED => $this->priceMoved($result),
            Placement::BUSY => $this->say('busy', __('Another bet of yours is being placed. Wait a moment.')),
            default => $this->refuse($result->refusal ?? new Refusal('refused', __('This bet could not be placed.'))),
        };
    }

    private function wasPlaced(Market $market, float $stake, Placement $result): void
    {
        $bet = $result->bet;
        $this->placed = [
            'multiplier' => $bet !== null && $bet->multiplier !== null ? $bet->multiplier : ($this->quote['multiplier'] ?? $this->estimate['multiplier'] ?? null),
            'payout' => $bet !== null && $bet->potentialPayout !== null ? $bet->potentialPayout : ($this->quote['payout'] ?? $this->estimate['payout'] ?? null),
            'estimate' => $bet !== null ? $bet->isEstimate : ! $market->usesQuote() || $this->poolFlow,
            'side' => $this->side,
            'stake' => $stake,
        ];
        $this->state = 'placed';
        $this->reset('quote', 'changed', 'estimate');

        $this->dispatch('wallet-refreshed');

        if (! $result->replayed) {
            $this->dispatch('kadi-track', name: 'bet_placed', params: ['product' => 'predict', 'bet_type' => $market->usesQuote() && ! $this->poolFlow ? 'fixed' : 'pool']);
        }
    }

    private function checking(?int $retryAfter): void
    {
        $this->state = 'checking';
        $this->say('unknown', __('We\'re checking your bet. If it went through, it will show in My bets.'), Refusal::RETRY, retryAfter: $retryAfter);
    }

    private function priceMoved(Placement $result): void
    {
        $this->quote = null;
        $this->changed = [
            'multiplier' => $result->multiplier ?? $result->quote?->multiplier,
            'quote' => $result->quote !== null && $result->quote->matches($this->marketId, $this->side, (float) $this->stake) ? self::quoteArray($result->quote) : null,
        ];
    }

    private function refuse(Refusal $refusal): void
    {
        $this->state = 'pick';

        if ($refusal->action === Refusal::NEW_QUOTE || $refusal->action === Refusal::CLOSED) {
            $this->quote = null;
        }

        $this->say($refusal->code, $refusal->message, $refusal->action, $refusal->amount, $refusal->retryAfter);
    }

    private function say(string $code, string $message, ?string $action = null, ?float $amount = null, ?int $retryAfter = null): void
    {
        $this->notice = [
            'code' => $code,
            'message' => $message,
            'action' => $action,
            'amount' => $amount,
            'retry_at' => $retryAfter !== null ? Date::now()->getTimestamp() + $retryAfter : null,
        ];
    }

    /**
     * The player, the market and the stake when a bet may be offered now, or a notice and nulls when not.
     *
     * @return array{0: ?User, 1: Market, 2: float}|array{0: null, 1: null, 2: null}
     */
    private function ready(): array
    {
        $user = auth()->user();
        $market = $this->market;

        if (! $user instanceof User || $market === null || $this->blocked !== null) {
            return [null, null, null];
        }

        if (! $market->takesBets(Date::now())) {
            $this->quote = null;
            $this->say('betting_closed', $market->unavailableReason(Date::now()) ?? __('Betting on this market has closed.'), Refusal::CLOSED);

            return [null, null, null];
        }

        if (! $market->sideAvailable($this->side, Date::now())) {
            $this->say('odds_unavailable', __('Pick a side that is on offer.'));

            return [null, null, null];
        }

        $stake = $this->stakeError($market);

        if (is_string($stake)) {
            $this->say('stake', $stake);

            return [null, null, null];
        }

        return [$user, $market, $stake];
    }

    /** The stake as a number when it is within the market's limits, else what is wrong with it. */
    private function stakeError(Market $market): float|string
    {
        $stake = Format::float(str_replace([',', ' '], '', $this->stake));

        if ($stake === null || $stake <= 0) {
            return __('Enter a stake.');
        }

        if (round($stake, 2) !== $stake) {
            return __('Stakes go to the cent at most.');
        }

        if ($market->minStake !== null && $stake < $market->minStake) {
            return __('The least you can stake is :amount.', ['amount' => Format::kes($market->minStake)]);
        }

        if ($market->maxStake !== null && $stake > $market->maxStake) {
            return __('The most you can stake is :amount.', ['amount' => Format::kes($market->maxStake)]);
        }

        return $stake;
    }

    /**
     * The open quote when it still matches what is on the slip and has time left on our clock. An expired one is
     * refused here without calling the API.
     *
     * @return array<string, mixed>|null
     */
    private function openQuote(Market $market, float $stake): ?array
    {
        $quote = $this->quote;

        if ($quote === null || $quote['market_id'] !== $market->id || $quote['side'] !== $this->side || abs($quote['stake'] - $stake) >= 0.005) {
            $this->quote = null;
            $this->say('quote_required', __('Get a price first.'), Refusal::NEW_QUOTE);

            return null;
        }

        if (CarbonImmutable::parse($quote['expires_at'])->lessThanOrEqualTo(Date::now())) {
            $this->quote = null;
            $this->say('quote_expired', __('That price has expired. Get a new one.'), Refusal::NEW_QUOTE);

            return null;
        }

        return $quote;
    }

    /**
     * The indicative or estimated figures for the stake on the slip. Two limits keep this cheap: per player and for
     * the whole site (half the API's shared read budget), so typing can never starve the feed. Over either, or on
     * any failure, the last figure stays.
     */
    private function refreshEstimate(): void
    {
        $user = auth()->user();
        $market = $this->market;

        if (! $user instanceof User || $market === null || ! in_array($this->side, Market::SIDES, true) || ! $market->takesBets(Date::now())) {
            return;
        }

        $stake = $this->stakeError($market);

        if (! is_float($stake)) {
            $this->estimate = null;

            return;
        }

        if (! $this->withinLimit("predict-limit:estimate:{$user->getKey()}", (int) config('predict.limits.estimates_per_player', 20))
            || ! $this->withinLimit('predict-limit:estimate:site', (int) config('predict.limits.estimates_site', 600))) {
            return;
        }

        $response = app(PredictApi::class)->estimate($market->id, $this->side, $stake);
        $multiplier = Format::float($response->data['estimated_multiplier'] ?? null);
        $payout = Format::float($response->data['estimated_payout'] ?? null);

        if ($response->ok() && $multiplier !== null && $payout !== null) {
            $this->estimate = ['multiplier' => $multiplier, 'payout' => $payout];
        } elseif ($response->refused() && ! $response->rateLimited()) {
            $this->refuse(RefusalMessage::for($response));
        }
    }

    /** @return array{id: string, market_id: int, side: string, stake: float, multiplier: float, payout: float, expires_at: string} */
    private static function quoteArray(Quote $quote): array
    {
        return [
            'id' => $quote->id,
            'market_id' => $quote->marketId,
            'side' => $quote->side,
            'stake' => $quote->stake,
            'multiplier' => $quote->multiplier,
            'payout' => $quote->potentialPayout,
            'expires_at' => $quote->expiresAt->toIso8601String(),
        ];
    }

    /** @return array{reference: string, market_id: int, side: string, stake: float, quote_id: ?string}|null */
    private function pendingHere(): ?array
    {
        $user = auth()->user();
        $pending = $user instanceof User ? PendingAttempt::get($user) : null;

        return $pending !== null && $pending['market_id'] === $this->marketId ? $pending : null;
    }

    /** @return list<int> */
    private static function quickStakes(Market $market): array
    {
        return array_values(array_filter(self::QUICK_STAKES, fn (int $amount) => ($market->minStake === null || $amount >= $market->minStake)
            && ($market->maxStake === null || $amount <= $market->maxStake)));
    }

    private static function amount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function withinLimit(string $key, int $perMinute): bool
    {
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
}
