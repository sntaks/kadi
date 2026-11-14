<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Support\InPlay;
use Illuminate\Database\Eloquent\Builder;

/**
 * Closes betting on open markets that may no longer be sold:
 *  - pre-match: odds not refreshed within the stale window (hours);
 *  - kicked off: closed, unless in play is on and the market may trade live (plan §4.3). A live market then closes
 *    when its live price is older than `live_stale_seconds`, in the match's late phase, or when the feed says the
 *    match is over. Score changes and decided lines are handled by the live tick itself.
 *
 * Runs every five minutes and at the end of every live tick, so a dead live feed closes markets within one tick.
 */
class SuspendStaleMarkets
{
    /**
     * @return array{kicked_off: int, stale: int, live_stale: int, late: int} The number of markets suspended for each reason.
     */
    public function handle(): array
    {
        $counts = ['kicked_off' => 0, 'stale' => 0, 'live_stale' => 0, 'late' => 0];
        $kickedOff = Market::query()->open()->whereIn('fixture_id', Fixture::query()->select('id')->where('kicks_off_at', '<=', now('UTC')));

        if (! InPlay::on()) {
            $counts['kicked_off'] = $this->close($kickedOff, SuspensionReason::KickedOff);
        } else {
            // Few markets are open on kicked-off matches at any time: sort them here, with each one's sport.
            $liveStaleBefore = now('UTC')->subSeconds((int) config('sportsbook.in_play.live_stale_seconds'));

            foreach ($kickedOff->with('fixture.competition.sport')->get() as $market) {
                $fixture = $market->fixture;
                $reason = match (true) {
                    ! InPlay::coversMarket($market, $fixture) => SuspensionReason::KickedOff,
                    $fixture->feed_completed_at !== null || now('UTC')->gte(InPlay::closesAt($fixture)) => SuspensionReason::LatePhase,
                    $market->priced_at === null || $market->priced_at->lt($liveStaleBefore) => SuspensionReason::LiveFeedStale,
                    default => null,
                };

                if ($reason !== null) {
                    $market->suspend($reason);
                    $counts[match ($reason) {
                        SuspensionReason::KickedOff => 'kicked_off',
                        SuspensionReason::LatePhase => 'late',
                        default => 'live_stale',
                    }]++;
                }
            }
        }

        $counts['stale'] = $this->close(
            Market::query()->open()->where(fn ($query) => $query
                ->whereNull('priced_at')
                ->orWhere('priced_at', '<', now('UTC')->subHours((int) config('sportsbook.odds.stale_after_hours')))),
            SuspensionReason::StaleOdds,
        );

        return $counts;
    }

    /**
     * @param  Builder<Market>  $markets
     */
    private function close($markets, SuspensionReason $reason): int
    {
        return $markets->update(['status' => MarketStatus::Suspended, 'suspension_reason' => $reason, 'updated_at' => now('UTC')]);
    }
}
