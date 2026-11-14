<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Models\Market;
use App\Sportsbook\OddsApi\OddsApi;

/**
 * Turns feed outcomes into bookmaker prices per line, for the consensus pricer. The bulk endpoint gives each book one
 * line per market (its main line); a match's alternate markets give each book many lines in one market. Both shapes
 * go through here. Quarter lines (2.25, -0.75) are always skipped: they half win or half lose, which we don't settle.
 *
 * @phpstan-import-type EventOddsData from OddsApi
 * @phpstan-import-type OutcomeData from OddsApi
 */
final class LinePrices
{
    /**
     * Each bookmaker's outcomes for one market key ('totals', 'alternate_totals'...).
     *
     * @param  EventOddsData  $event
     * @return array<string, list<OutcomeData>>
     */
    public static function outcomesByBook(array $event, string $marketKey): array
    {
        $outcomes = [];

        foreach ($event['bookmakers'] as $bookmaker) {
            foreach ($bookmaker['markets'] as $market) {
                if ($market['key'] === $marketKey) {
                    $outcomes[$bookmaker['key']] = $market['outcomes'];
                }
            }
        }

        return $outcomes;
    }

    /**
     * Over/Under prices per line. With $team, only that team's goal lines (the outcome's `description`); without,
     * only match lines (no description).
     *
     * @param  array<string, list<OutcomeData>>  $outcomesByBook
     * @return array<string, array<string, array{over?: float, under?: float}>> line => book => side => price
     */
    public static function overUnder(array $outcomesByBook, ?string $team = null): array
    {
        $prices = [];

        foreach ($outcomesByBook as $book => $outcomes) {
            foreach ($outcomes as $outcome) {
                $side = strtolower($outcome['name']);

                if (! isset($outcome['point']) || ! in_array($side, ['over', 'under'], true) || Market::isQuarterLine($outcome['point'])
                    || ($outcome['description'] ?? null) !== $team) {
                    continue;
                }

                $prices[Market::formatLine($outcome['point'])][$book][$side] = (float) $outcome['price'];
            }
        }

        return $prices;
    }

    /**
     * Handicap prices per line, keyed by the home side's line. A book counts for a line only when it prices the away
     * side at the mirrored line (home -1.5 with away +1.5).
     *
     * @param  array<string, list<OutcomeData>>  $outcomesByBook
     * @return array<string, array<string, array{home: float, away: float}>> home line => book => side => price
     */
    public static function handicaps(array $outcomesByBook, string $homeTeam, string $awayTeam): array
    {
        $prices = [];

        foreach ($outcomesByBook as $book => $outcomes) {
            $away = [];

            foreach ($outcomes as $outcome) {
                if ($outcome['name'] === $awayTeam && isset($outcome['point'])) {
                    $away[Market::formatLine($outcome['point'])] = (float) $outcome['price'];
                }
            }

            foreach ($outcomes as $outcome) {
                if ($outcome['name'] !== $homeTeam || ! isset($outcome['point']) || Market::isQuarterLine($outcome['point'])) {
                    continue;
                }

                $line = Market::formatLine($outcome['point']);
                $mirrored = Market::formatLine(-(float) $outcome['point']);

                if (isset($away[$mirrored])) {
                    $prices[$line][$book] = ['home' => (float) $outcome['price'], 'away' => $away[$mirrored]];
                }
            }
        }

        return $prices;
    }
}
