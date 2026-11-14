<?php

namespace App\Sportsbook\Jobs;

use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Betting\Payout;
use App\Sportsbook\Betting\PlaceBet;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Promotions\MultibetBonus;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Sends a held live bet once its delay is over (plan §4.4). Every pick is checked again first, with its selection
 * locked: still bettable (market open, match before its late phase), live price fresh, score unchanged since the bet
 * was placed, and any price move acceptable under the bet's odds-change setting. A failed check rejects the bet
 * without calling the API (nothing was charged; a free bet is given back). A price that moved down and was accepted
 * is applied to the bet; one that moved up keeps the price the player asked for.
 *
 * Then the normal placement, on the bet's own product (`sportsbook_live`), with the player's cached token. A token
 * the API no longer accepts (the player logged out) rejects the bet: there is no session to renew it for.
 *
 * Never retried: an unknown outcome is ReconcileWager's, as for any placement. Carries the bet id only. Keep the
 * nested limits: timeout 60s < worker --timeout 90s < retry_after 120s.
 */
class AcceptInPlayBet implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $betId)
    {
        $this->onQueue((string) config('sportsbook.wagers.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return (string) $this->betId;
    }

    public function handle(WagerPlacement $placement, BettableSelections $bettable): void
    {
        $bet = Bet::query()->with(['legs', 'user'])->find($this->betId);

        if ($bet === null || $bet->status !== BetStatus::Pending || $bet->accept_after === null || $bet->accept_after->isFuture()) {
            return;
        }

        if ($bet->user === null) {
            $placement->rejectUnsent($bet, 'player_token_invalid');

            return;
        }

        $problem = DB::transaction(fn (): ?string => $this->check($bet, $bettable));

        if ($problem !== null) {
            $placement->rejectUnsent($bet, $problem);

            return;
        }

        $token = $placement->token($bet->user);

        if (is_array($token)) {
            $placement->rejectUnsent($bet, 'player_token_invalid');

            return;
        }

        $placement->place($bet->user, $bet->fresh(['legs']) ?? $bet, $token, renewToken: false);
    }

    /**
     * Null when every pick still holds; otherwise the reason code the bet is rejected with.
     */
    private function check(Bet $bet, BettableSelections $bettable): ?string
    {
        $selections = Selection::query()->whereKey($bet->legs->pluck('selection_id')->all())->lockForUpdate()
            ->with('market.fixture.competition.sport')->get()->keyBy('id');
        $staleBefore = now('UTC')->subSeconds((int) config('sportsbook.in_play.live_stale_seconds'));
        $repriced = false;

        foreach ($bet->legs as $leg) {
            $selection = $selections->get($leg->selection_id);

            if ($selection === null || $bettable->unavailableReason($selection) !== null) {
                return $leg->in_play ? 'live_market_closed' : 'selection_closed';
            }

            if (! $leg->in_play) {
                continue;
            }

            if ($selection->market->priced_at === null || $selection->market->priced_at->lt($staleBefore)) {
                return 'live_price_stale';
            }

            if (PlaceBet::score($selection->market->fixture) !== $leg->score_at_placement) {
                return 'live_score_changed';
            }

            $current = (string) $selection->price();

            if ($current === (string) $leg->price) {
                continue;
            }

            if (! $bet->odds_change_policy->accepts((string) $leg->price, $current)) {
                return 'live_price_changed';
            }

            // Moved down and accepted: the bet takes the new price. Moved up: it keeps the one asked for.
            if ((float) $current < (float) $leg->price) {
                $leg->update(['price' => $current]);
                $repriced = true;
            }
        }

        if ($repriced) {
            $this->reprice($bet);
        }

        return null;
    }

    /** Combined odds, possible payout and multibet bonus from the legs' prices, with the in-play cap. */
    private function reprice(Bet $bet): void
    {
        $prices = $bet->legs->map(fn (BetLeg $leg): string => (string) $leg->price);
        $odds = Payout::combinedOdds($prices);
        $cap = $bet->payoutCap();
        $payout = Payout::for($bet->stake, $odds, $cap);
        $bonusPercent = $bet->isFreeBet() ? 0 : min($bet->bonus_percent, MultibetBonus::percentFor($prices));

        $bet->update([
            'total_odds' => $odds,
            'bonus_percent' => $bonusPercent,
            'potential_payout' => $bet->isFreeBet() ? $payout - $bet->stake : $payout + MultibetBonus::amount($bet->stake, $payout, $bonusPercent, $cap),
        ]);
    }
}
