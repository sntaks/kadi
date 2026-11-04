<?php

namespace App\Sportsbook\Trading;

use App\Models\User;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Support\StaffActor;

/**
 * An admin reopens a suspended market, provided it could legitimately take bets right now.
 */
class ReopenMarket
{
    /**
     * @throws MarketCannotBeReopened
     */
    public function handle(Market $market, User|StaffActor $admin): void
    {
        if ($market->fixture->hasKickedOff()) {
            throw MarketCannotBeReopened::kickedOff();
        }

        if ($market->priced_at === null || ! $market->selections()->exists()) {
            throw MarketCannotBeReopened::unpriced();
        }

        if ($this->isStale($market)) {
            throw MarketCannotBeReopened::staleOdds();
        }

        $market->reopen();

        if ($market->type === MarketType::MatchResult) {
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

    private function isStale(Market $market): bool
    {
        return $market->priced_at === null
            || $market->priced_at->lt(now('UTC')->subHours((int) config('sportsbook.odds.stale_after_hours')));
    }
}
