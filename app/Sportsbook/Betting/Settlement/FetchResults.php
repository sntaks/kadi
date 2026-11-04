<?php

namespace App\Sportsbook\Betting\Settlement;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\OddsApi\CreditBudgetExceeded;
use App\Sportsbook\OddsApi\OddsApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches full-time scores from the paid feed (2 credits a call) and settles the matches they finish.
 *
 * Credits are only spent for a competition that is set to settle automatically, has a match that kicked off at
 * least `result_after_minutes` ago with open bets on it and no result yet, and was last asked more than
 * `scores_check_interval_hours` ago (decision 33). Matches older than the feed's reach (`scores_days_from`) are left
 * for an admin.
 */
class FetchResults
{
    public function __construct(private OddsApi $oddsApi, private RecordResult $recordResult) {}

    /**
     * @return array{competitions: int, results: int}
     */
    public function handle(): array
    {
        $counts = ['competitions' => 0, 'results' => 0];

        foreach ($this->competitionsDue() as $competition) {
            try {
                $games = $this->oddsApi->scores($competition->odds_api_key, (int) config('sportsbook.settlement.scores_days_from'));
            } catch (CreditBudgetExceeded $e) {
                Log::warning("Results not fetched: {$e->getMessage()}");

                break;
            } catch (Throwable $e) {
                Log::warning("Results for {$competition->odds_api_key} not fetched: ".class_basename($e));

                continue;
            }

            // Stamped only once the paid call succeeded: a refused or failed call spent nothing, so the next
            // hourly run asks again instead of leaving finished matches unsettled for scores_check_interval_hours.
            $competition->update(['scores_checked_at' => now('UTC')]);
            $counts['competitions']++;

            foreach ($games as $game) {
                // One game that cannot be recorded never throws away the rest of a paid response.
                try {
                    $counts['results'] += $this->record($competition, $game) ? 1 : 0;
                } catch (Throwable $e) {
                    report($e);
                    Log::warning("Result for {$competition->odds_api_key} game {$game['id']} not recorded: ".class_basename($e));
                }
            }
        }

        return $counts;
    }

    /**
     * @return iterable<Competition>
     */
    private function competitionsDue(): iterable
    {
        return Competition::query()
            ->where('auto_settle', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('scores_checked_at')
                ->orWhere('scores_checked_at', '<=', now('UTC')->subHours((int) config('sportsbook.settlement.scores_check_interval_hours'))))
            ->whereHas('fixtures', fn (Builder $fixtures) => self::awaitingResult($fixtures))
            ->get();
    }

    /**
     * Fixtures the feed should have a result for that bets are still waiting on.
     *
     * @param  Builder<Fixture>  $fixtures
     * @return Builder<Fixture>
     */
    public static function awaitingResult(Builder $fixtures): Builder
    {
        return $fixtures
            ->needingResult()
            ->where('kicks_off_at', '>=', now('UTC')->subDays((int) config('sportsbook.settlement.scores_days_from')));
    }

    /**
     * Record one completed game's full-time score, if it is one of ours and still has no result.
     *
     * @param  array{id: string, completed: bool, home_team: string, away_team: string, scores: list<array{name: string, score: string}>|null}  $game
     */
    private function record(Competition $competition, array $game): bool
    {
        if (! $game['completed'] || $game['scores'] === null) {
            return false;
        }

        $fixture = Fixture::query()->where('competition_id', $competition->id)->where('external_id', $game['id'])->first();

        if ($fixture === null || $fixture->result()->exists()) {
            return false;
        }

        $scores = collect($game['scores'])->mapWithKeys(fn (array $score): array => [$score['name'] => $score['score']]);
        $home = $scores->get($game['home_team']);
        $away = $scores->get($game['away_team']);

        if (! is_numeric($home) || ! is_numeric($away)) {
            Log::warning("Scores for fixture {$fixture->id} do not name both teams; left for an admin.");

            return false;
        }

        $this->recordResult->handle($fixture, ['home_score' => (int) $home, 'away_score' => (int) $away], FixtureResult::SOURCE_FEED);

        return true;
    }
}
