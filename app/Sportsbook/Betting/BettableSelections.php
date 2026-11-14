<?php

namespace App\Sportsbook\Betting;

use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Support\InPlay;
use Illuminate\Database\Eloquent\Collection;

/**
 * Loads selections with everything needed to judge whether they can be bet on right now.
 */
class BettableSelections
{
    /**
     * @param  list<int>  $selectionIds
     * @return Collection<int, Selection> Keyed by selection id.
     */
    public function find(array $selectionIds): Collection
    {
        if ($selectionIds === []) {
            return new Collection;
        }

        return Selection::query()
            ->whereKey($selectionIds)
            ->with(['market.fixture.competition.sport'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Whether this selection is on a market trading in play right now (plan §4.4): in play on, a covered market of a
     * match in play, before its late phase. Whether the market is open is checked separately.
     */
    public function isLive(Selection $selection): bool
    {
        $fixture = $selection->market->fixture;

        return $fixture->hasKickedOff()
            && InPlay::on()
            && InPlay::coversMarket($selection->market, $fixture)
            && $fixture->feed_completed_at === null
            && now('UTC')->lt(InPlay::closesAt($fixture));
    }

    /**
     * Why a selection cannot be bet on right now, or null when it can. The message is translated where it is shown.
     */
    public function unavailableReason(Selection $selection): ?string
    {
        $market = $selection->market;

        return match (true) {
            $market->fixture->status === FixtureStatus::Cancelled => 'This match was called off.',
            $market->fixture->hasKickedOff() && ! $this->isLive($selection) => 'This match has started.',
            ! $market->isOpen() => 'This market is suspended.',
            $selection->price() === null => 'This selection has no price.',
            default => null,
        };
    }
}
