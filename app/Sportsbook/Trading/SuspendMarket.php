<?php

namespace App\Sportsbook\Trading;

use App\Models\User;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Support\StaffActor;

/**
 * An admin closes a market to new bets. It stays closed through odds syncs until an admin reopens it.
 */
class SuspendMarket
{
    /**
     * Suspending a 1X2 also suspends the markets derived from it, since their prices depend on it.
     */
    public function handle(Market $market, User|StaffActor $admin, string $reason): void
    {
        $market->suspend(SuspensionReason::Manual);

        if ($market->type === MarketType::MatchResult) {
            $market->fixture->markets()
                ->where('source', MarketSource::Derived)
                ->open()
                ->get()
                ->each(fn (Market $derived) => $derived->suspend(SuspensionReason::Manual));
        }

        activity('odds')
            ->causedBy(StaffActor::causer($admin))
            ->performedOn($market)
            ->event('market-suspended')
            ->withProperties(StaffActor::with($admin, ['fixture' => $market->fixture->name(), 'market' => $market->name(), 'reason' => $reason]))
            ->log('Market suspended');
    }
}
