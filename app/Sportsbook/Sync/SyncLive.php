<?php

namespace App\Sportsbook\Sync;

use App\Events\LiveFixturesUpdated;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\OddsApi\CreditBudgetExceeded;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\Pricing\ConsensusPricer;
use App\Sportsbook\Support\InPlay;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One in-play tick (plan §4.2): for each in-play competition with a match on, the live scores first (a change holds
 * that match's markets), then the live odds of its in-play markets, priced with the in-play margin. Lines the score
 * has decided close; prices that stop arriving go stale (SuspendStaleMarkets). Matches in the same competition share
 * the calls, so the cost is per competition in play, not per match. Nothing is called when nothing is live.
 *
 * @phpstan-import-type ScoreData from OddsApi
 */
class SyncLive
{
    public function __construct(
        private readonly OddsApi $oddsApi,
        private readonly PriceFeedEvent $priceFeedEvent,
        private readonly SuspendStaleMarkets $suspendStaleMarkets,
    ) {}

    /**
     * @return array{competitions: int, fixtures: int}
     */
    public function handle(): array
    {
        $counts = ['competitions' => 0, 'fixtures' => 0];

        if (! InPlay::on()) {
            return $counts;
        }

        $pricer = new ConsensusPricer(
            margin: (float) config('sportsbook.in_play.margin'),
            minimumBookmakers: (int) config('sportsbook.odds.minimum_bookmakers'),
        );

        $live = InPlay::liveFixtures(Fixture::query())->with(['competition.sport', 'markets.selections'])->get()->groupBy('competition_id');

        foreach ($live as $fixtures) {
            /** @var Collection<int, Fixture> $fixtures */
            $competition = $fixtures->first()->competition;

            try {
                $this->tick($competition, $fixtures, $pricer);
            } catch (CreditBudgetExceeded $exception) {
                // The live allowance is spent: live markets go stale and close; nothing else is affected.
                Log::warning("In-play tick stopped: {$exception->getMessage()}");

                break;
            } catch (Throwable $exception) {
                Log::warning("In-play tick for {$competition->odds_api_key} failed: ".class_basename($exception));

                continue;
            }

            $counts['competitions']++;
            $counts['fixtures'] += $fixtures->count();
        }

        // Every tick also closes what went stale or late, so a dead feed closes markets within one tick.
        $this->suspendStaleMarkets->handle();

        return $counts;
    }

    /**
     * @param  Collection<int, Fixture>  $fixtures
     */
    private function tick(Competition $competition, Collection $fixtures, ConsensusPricer $pricer): void
    {
        $profile = SportProfile::forSport($competition->sport);
        $byEventId = $fixtures->keyBy('external_id');

        foreach ($this->oddsApi->scores($competition->odds_api_key, 0, OddsApiPurpose::Live) as $game) {
            if (($fixture = $byEventId->get($game['id'])) !== null) {
                $this->applyScore($fixture, $game);
            }
        }

        $types = InPlay::marketTypes($profile->slug);
        $playing = $fixtures->filter(fn (Fixture $fixture): bool => $fixture->feed_completed_at === null);

        if ($types !== [] && $playing->isNotEmpty()) {
            $events = $this->oddsApi->odds($competition->odds_api_key, array_map(fn (MarketType $type): string => $type->value, $types), OddsApiPurpose::Live);

            foreach ($events as $event) {
                $fixture = $playing->firstWhere('external_id', $event['id']);

                if ($fixture !== null && $fixture->hasKickedOff()) {
                    DB::transaction(fn () => $this->priceFeedEvent->handle($fixture, $event, $types, $pricer, $profile, live: true));
                }
            }
        }

        event(new LiveFixturesUpdated($fixtures->map(fn (Fixture $fixture): array => $this->snapshot($fixture->fresh(['markets.selections']) ?? $fixture, $types))->values()->all()));
    }

    /**
     * A new score holds the match's markets (ScoreChanged) until prices fetched after the hold arrive, and closes
     * the goal lines it has decided. A finished match (by the feed) closes for good.
     *
     * @param  ScoreData  $game
     */
    private function applyScore(Fixture $fixture, array $game): void
    {
        if ($game['completed']) {
            $fixture->update(['feed_completed_at' => now('UTC')]);
            $this->suspend($fixture->markets->filter->isOpen(), SuspensionReason::LatePhase);

            return;
        }

        $scores = collect($game['scores'] ?? [])->pluck('score', 'name');

        if (! is_numeric($scores[$fixture->home_team] ?? null) || ! is_numeric($scores[$fixture->away_team] ?? null)) {
            return;
        }

        [$home, $away] = [(int) $scores[$fixture->home_team], (int) $scores[$fixture->away_team]];
        $changed = $fixture->home_score === null ? $home + $away > 0 : ($home !== $fixture->home_score || $away !== $fixture->away_score);

        $fixture->update([
            'home_score' => $home,
            'away_score' => $away,
            'score_updated_at' => now('UTC'),
            'score_changed_at' => $changed ? now('UTC') : $fixture->score_changed_at,
        ]);

        if ($changed) {
            $this->suspend($fixture->markets->filter->isOpen(), SuspensionReason::ScoreChanged);
        }

        // An Over/Under line the total has passed can only end one way: close it for the rest of the match.
        $this->suspend($fixture->markets->filter(fn (Market $market): bool => $market->type === MarketType::Totals
            && $market->line !== null && $home + $away > (float) $market->line
            && $market->suspension_reason !== SuspensionReason::LineDecided), SuspensionReason::LineDecided);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Market>  $markets
     */
    private function suspend(\Illuminate\Support\Collection $markets, SuspensionReason $reason): void
    {
        $markets->each(fn (Market $market) => $market->suspension_reason === SuspensionReason::Manual ? null : $market->suspend($reason));
    }

    /**
     * What open pages need to redraw a match: the score and its in-play markets' prices.
     *
     * @param  list<MarketType>  $types
     * @return array{id: int, home_score: int|null, away_score: int|null, markets: list<array{id: int, open: bool, selections: array<int, string|null>}>}
     */
    private function snapshot(Fixture $fixture, array $types): array
    {
        return [
            'id' => $fixture->id,
            'home_score' => $fixture->home_score,
            'away_score' => $fixture->away_score,
            'markets' => $fixture->markets
                ->filter(fn (Market $market): bool => in_array($market->type, $types, true))
                ->map(fn (Market $market): array => [
                    'id' => $market->id,
                    'open' => $market->status === MarketStatus::Open,
                    'selections' => $market->selections->mapWithKeys(fn ($selection) => [$selection->id => $selection->price()])->all(),
                ])->values()->all(),
        ];
    }
}
