<?php

namespace App\Sportsbook\Betting;

use App\Activity\PlayerActivity;
use App\Models\User;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\BetType;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Enums\OddsChangePolicy;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Promotions\MultibetBonus;
use App\Sportsbook\Support\Money;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Places the player's slip as one bet: a single for one pick, a multi for more. The stake is a Kadi API wager
 * (plan §5.3), paid from the one Kadi wallet:
 *
 * 1. Everything is checked again on the server inside one database transaction, with the picked selections locked
 *    so an odds sync cannot change a price halfway: every pick must still be open, each match may appear once, and
 *    any price that moved must be acceptable under the player's odds-change setting. The bet is saved as `pending`
 *    at the current prices, with a new UUID wager reference.
 * 2. After the commit, POST /wagers takes the stake (WagerPlacement): the bet is then open, rejected (nothing
 *    charged; the reason is kept), or still pending when the API did not answer, until it is looked up.
 *
 * With a free bet, its whole amount is the stake (funding free_bet) and nothing leaves the wallet; the slip's total
 * odds must reach the free bet's minimum. A cash multi records the multibet bonus it qualifies for; a free bet gets
 * none. A slip key makes it safe to submit twice: the second submission returns the bet the first one made.
 */
class PlaceBet
{
    /** No 0/O, 1/I/L: references are read out to support. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const REFERENCE_LENGTH = 8;

    public function __construct(private BettableSelections $bettableSelections, private WagerPlacement $placement) {}

    /**
     * @param  array<int, mixed>  $picks  [{fixture_id, selection_id, price}] as the slip holds them.
     * @param  mixed  $stake  Whole shillings; ignored with a free bet, whose amount is the stake.
     * @return Bet Open, or pending while the wallet's answer is checked.
     *
     * @throws BetNotPlaced
     */
    public function handle(User $user, array $picks, mixed $stake, ?OddsChangePolicy $policy = null, ?string $clientKey = null, ?int $freeBetId = null, ?string $ip = null): Bet
    {
        if (($refusal = $this->placement->refusalFor($user)) !== null) {
            throw self::refused($refusal);
        }

        if ($clientKey !== null && ($placed = Bet::query()->where('user_id', $user->id)->where('client_key', $clientKey)->first()) !== null) {
            return $placed;
        }

        $policy ??= OddsChangePolicy::tryFrom((string) $user->odds_change_policy) ?? OddsChangePolicy::Higher;
        $stakeCents = $freeBetId === null ? $this->stake($stake) : 0;

        // The wallet session first: if it cannot be had, nothing is saved.
        $token = $this->placement->token($user, $ip);

        if (is_array($token)) {
            throw self::refused($token);
        }

        try {
            $bet = DB::transaction(fn (): Bet => $this->save($user, $picks, $stakeCents, $policy, $clientKey, $freeBetId));
        } catch (UniqueConstraintViolationException $e) {
            // The same slip submitted twice at once: the other request saved it.
            $placed = $clientKey === null ? null : Bet::query()->where('user_id', $user->id)->where('client_key', $clientKey)->first();

            return $placed ?? throw $e;
        }

        $bet = $this->placement->place($user, $bet, $token, $ip);

        if ($bet instanceof Bet && $bet->status === BetStatus::Rejected) {
            throw self::refused(WagerPlacement::refusal((string) $bet->reject_reason));
        }

        /** @var Bet $bet */
        app(PlayerActivity::class)->record('bet_placed', $user, ['status' => $bet->status->value], $bet);

        return $bet;
    }

    /**
     * @param  array{0: string, 1: string}  $refusal
     */
    private static function refused(array $refusal): BetNotPlaced
    {
        return new BetNotPlaced(match ($refusal[0]) {
            WagerPlacement::REFUSED_NOT_ALLOWED => BetNotPlaced::NOT_ALLOWED,
            WagerPlacement::REFUSED_FUNDS => BetNotPlaced::INSUFFICIENT_FUNDS,
            WagerPlacement::REFUSED_STAKE => BetNotPlaced::STAKE,
            default => BetNotPlaced::WALLET,
        }, $refusal[1]);
    }

    /**
     * @param  array<int, mixed>  $picks
     *
     * @throws BetNotPlaced
     */
    private function save(User $user, array $picks, int $stake, OddsChangePolicy $policy, ?string $clientKey, ?int $freeBetId): Bet
    {
        $requested = collect($picks)
            ->filter(fn (mixed $pick): bool => is_array($pick) && is_numeric($pick['selection_id'] ?? null))
            ->map(fn (array $pick): int => (int) $pick['selection_id'])
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            throw new BetNotPlaced(BetNotPlaced::EMPTY_SLIP, __('Add at least one selection to your slip.'));
        }

        if ($requested->count() > (int) config('sportsbook.limits.max_selections')) {
            throw new BetNotPlaced(BetNotPlaced::EMPTY_SLIP, __('A bet can have at most :max selections.', ['max' => config('sportsbook.limits.max_selections')]));
        }

        $freeBet = $freeBetId === null ? null : $this->freeBet($user, $freeBetId);
        $stake = $freeBet->amount ?? $stake;

        // Locked until the bet is written, so a price cannot change between checking it and recording it.
        Selection::query()->whereKey($requested->all())->lockForUpdate()->get();

        $quote = BetSlipQuote::for($picks, (string) Money::toShillings($stake), $this->bettableSelections, $freeBet !== null);

        if (count($quote->lines) !== $requested->count()) {
            throw new BetNotPlaced(BetNotPlaced::DUPLICATE_MATCH, __('Each match can only appear once on a bet, and every selection must still exist.'));
        }

        foreach ($quote->lines as $line) {
            if ($line['status'] === 'unavailable') {
                throw new BetNotPlaced(BetNotPlaced::UNAVAILABLE, __('Remove the selections that are closed, then place your bet.'));
            }

            if ($line['status'] === 'price_changed' && ! $policy->accepts($line['seen_price'], (string) $line['selection']->price())) {
                throw new BetNotPlaced(BetNotPlaced::PRICE_CHANGED, __('Some odds have changed. Check the new odds, then place your bet.'));
            }
        }

        if ($quote->stakeErrors !== []) {
            throw new BetNotPlaced(BetNotPlaced::STAKE, $quote->stakeErrors[0]);
        }

        $prices = collect($quote->lines)->map(fn (array $line): string => (string) $line['selection']->price());
        $odds = Payout::combinedOdds($prices);

        if ($freeBet !== null && (int) round((float) $odds * 100) < (int) round((float) $freeBet->min_odds * 100)) {
            throw new BetNotPlaced(BetNotPlaced::FREE_BET_MIN_ODDS, __('This free bet needs total odds of at least :odds.', ['odds' => $freeBet->min_odds]));
        }

        $payout = Payout::for($stake, $odds);
        $bonusPercent = $freeBet === null ? MultibetBonus::percentFor($prices) : 0;

        $bet = Bet::create([
            'user_id' => $user->id,
            'free_bet_id' => $freeBet?->id,
            'reference' => $this->reference(),
            'wager_reference' => (string) Str::uuid(),
            'type' => count($quote->lines) === 1 ? BetType::Single : BetType::Multi,
            'status' => BetStatus::Pending,
            'stake' => $stake,
            'total_odds' => $odds,
            'potential_payout' => $freeBet !== null ? $payout - $stake : $payout + MultibetBonus::amount($stake, $payout, $bonusPercent),
            'bonus_percent' => $bonusPercent,
            'odds_change_policy' => $policy,
            'client_key' => $clientKey,
            'placed_at' => now('UTC'),
        ]);

        foreach ($quote->lines as $line) {
            $selection = $line['selection'];
            $market = $selection->market;

            $bet->legs()->create([
                'selection_id' => $selection->id,
                'market_id' => $market->id,
                'fixture_id' => $market->fixture_id,
                'fixture_name' => $market->fixture->name(),
                'kicks_off_at' => $market->fixture->kicks_off_at,
                'market_type' => $market->type,
                'market_line' => $market->line,
                'market_name' => $market->name(),
                'outcome' => $selection->outcome,
                'selection_name' => $selection->name,
                'price' => $selection->price(),
                'status' => BetLegStatus::Open,
            ]);
        }

        // Held for this bet; given back if the wallet refuses it.
        $freeBet?->update(['status' => FreeBetStatus::Used, 'used_at' => now('UTC')]);

        return $bet;
    }

    /**
     * The player's free bet, locked so it can only be used once.
     *
     * @throws BetNotPlaced
     */
    private function freeBet(User $user, int $freeBetId): FreeBet
    {
        $freeBet = FreeBet::query()->whereKey($freeBetId)->where('user_id', $user->id)->lockForUpdate()->first();

        if ($freeBet === null || ! $freeBet->isUsable()) {
            throw new BetNotPlaced(BetNotPlaced::FREE_BET_UNAVAILABLE, __('That free bet can no longer be used.'));
        }

        return $freeBet;
    }

    /**
     * The stake in cents. Whole shillings only; the limits are checked with the slip.
     *
     * @throws BetNotPlaced
     */
    private function stake(mixed $stake): int
    {
        if (! is_scalar($stake) || ! preg_match('/^\d+$/', trim((string) $stake))) {
            throw new BetNotPlaced(BetNotPlaced::STAKE, __('Enter your stake in whole shillings.'));
        }

        return Money::fromShillings((int) trim((string) $stake));
    }

    private function reference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = '';

            for ($i = 0; $i < self::REFERENCE_LENGTH; $i++) {
                $reference .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            if (! Bet::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique bet reference.');
    }
}
