<?php

namespace App\Sportsbook\Trading;

use App\Models\User;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Support\InPlay;
use App\Sportsbook\Support\StaffActor;

/**
 * An admin reopens a suspended market, provided it could legitimately take bets right now. A started match's market
 * can be reopened only while it trades in play (plan §4.8): covered, before its late phase, with a fresh live price,
 * no score change still held, and a line the score has not decided. Everything else on a started match stays closed.
 */
class ReopenMarket
{
    /**
     * @throws MarketCannotBeReopened
     */
    public function handle(Market $market, User|StaffActor $admin): void
    {
        $fixture = $market->fixture;
        $live = $fixture->hasKickedOff();

        if ($live && ! (InPlay::isTrading($fixture) && InPlay::coversMarket($market, $fixture))) {
            throw MarketCannotBeReopened::kickedOff();
        }

        if ($market->priced_at === null || ! $market->selections()->exists()) {
            throw MarketCannotBeReopened::unpriced();
        }

        if ($live) {
            $this->ensureLiveCanReopen($market);
        } elseif ($this->isStale($market)) {
            throw MarketCannotBeReopened::staleOdds();
        }

        $market->reopen();

        // Derived markets never trade in play.
        if ($market->type === MarketType::MatchResult && ! $live) {
            $this->reopenDerivedMarkets($market);
        }

        activity('odds')
            ->causedBy(StaffActor::causer($admin))
            ->performedOn($market)
            ->event('market-reopened')
            ->withProperties(StaffActor::with($admin, ['fixture' => $market->fixture->name(), 'market' => $market->name()]))
            ->log('Market reopened');
    }

    /**
     * Reopen the derived markets that were suspended by hand along with this 1X2, when they are freshly priced.
     */
    private function reopenDerivedMarkets(Market $matchResult): void
    {
        $matchResult->fixture->markets()
            ->where('source', MarketSource::Derived)
            ->where('status', MarketStatus::Suspended)
            ->where('suspension_reason', SuspensionReason::Manual)
            ->whereHas('selections')
            ->get()
            ->reject(fn (Market $derived): bool => $derived->priced_at === null || $this->isStale($derived))
            ->each(fn (Market $derived) => $derived->reopen());
    }

    /**
     * @throws MarketCannotBeReopened
     */
    private function ensureLiveCanReopen(Market $market): void
    {
        if ($market->suspension_reason === SuspensionReason::LineDecided) {
            throw MarketCannotBeReopened::lineDecided();
        }

        $held = $market->fixture->score_changed_at?->addSeconds((int) config('sportsbook.in_play.score_change_hold_seconds'));

        if ($held !== null && now('UTC')->lt($held)) {
            throw MarketCannotBeReopened::scoreChangeHeld();
        }

        if ($market->priced_at === null || $market->priced_at->lt(now('UTC')->subSeconds((int) config('sportsbook.in_play.live_stale_seconds')))) {
            throw MarketCannotBeReopened::liveStale();
        }
    }

    private function isStale(Market $market): bool
    {
        return $market->priced_at === null
            || $market->priced_at->lt(now('UTC')->subHours((int) config('sportsbook.odds.stale_after_hours')));
    }
}
