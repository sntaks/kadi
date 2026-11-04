<?php

namespace App\Sportsbook\Betting;

use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Fixture;
use Carbon\CarbonImmutable;

/**
 * What the book stands to pay on each match still waiting on a result, pick by pick.
 *
 * A pick's payout is the sum of the potential payouts of the open bets on it. For a multi that assumes every other
 * leg wins, so it is the worst case, not the expected cost. A slip holds at most one pick per match, so each bet is
 * counted once per match.
 *
 * Stakes are cash taken: a free bet's stake was never the player's money, and its potential payout is already only
 * the winnings above it.
 */
class Exposure
{
    /**
     * Matches with the largest worst-case payout first.
     *
     * @return list<array{fixture_id: int, match: string, competition: string, kicks_off_at: CarbonImmutable, bets: int, stakes: int, worst_pick: string, worst_payout: int, picks: list<array{pick: string, bets: int, stakes: int, payout: int}>}>
     */
    public function byMatch(int $limit = 100): array
    {
        $rows = BetLeg::query()
            ->join('sb_bets', 'sb_bets.id', '=', 'sb_bet_legs.bet_id')
            ->join('sb_fixtures', 'sb_fixtures.id', '=', 'sb_bet_legs.fixture_id')
            ->leftJoin('sb_fixture_results', 'sb_fixture_results.fixture_id', '=', 'sb_bet_legs.fixture_id')
            // A bet still being checked may well be placed: it counts until the API says otherwise.
            ->whereIn('sb_bets.status', [BetStatus::Open, BetStatus::Pending])
            ->where('sb_bet_legs.status', BetLegStatus::Open)
            ->where('sb_fixtures.status', FixtureStatus::Scheduled)
            ->whereNull('sb_fixture_results.id')
            ->groupBy('sb_bet_legs.fixture_id', 'sb_bet_legs.market_name', 'sb_bet_legs.selection_name')
            ->selectRaw('sb_bet_legs.fixture_id, sb_bet_legs.market_name, sb_bet_legs.selection_name, count(*) as bets, sum(case when sb_bets.free_bet_id is null then sb_bets.stake else 0 end) as stakes, sum(sb_bets.potential_payout) as payout')
            ->toBase()
            ->get();

        $picksByFixture = [];

        foreach ($rows as $row) {
            $picksByFixture[(int) $row->fixture_id][] = [
                'pick' => "{$row->market_name}: {$row->selection_name}",
                'bets' => (int) $row->bets,
                'stakes' => (int) $row->stakes,
                'payout' => (int) $row->payout,
            ];
        }

        $matches = [];

        foreach (Fixture::query()->with('competition')->findMany(array_keys($picksByFixture)) as $fixture) {
            $picks = $picksByFixture[$fixture->id];
            usort($picks, fn (array $a, array $b): int => $b['payout'] <=> $a['payout']);

            $matches[] = [
                'fixture_id' => $fixture->id,
                'match' => $fixture->name(),
                'competition' => $fixture->competition->name,
                'kicks_off_at' => $fixture->kicks_off_at,
                'bets' => array_sum(array_column($picks, 'bets')),
                'stakes' => array_sum(array_column($picks, 'stakes')),
                'worst_pick' => $picks[0]['pick'],
                'worst_payout' => $picks[0]['payout'],
                'picks' => $picks,
            ];
        }

        usort($matches, fn (array $a, array $b): int => $b['worst_payout'] <=> $a['worst_payout']);

        return array_slice($matches, 0, $limit);
    }
}
