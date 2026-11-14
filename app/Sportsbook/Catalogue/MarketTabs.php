<?php

namespace App\Sportsbook\Catalogue;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Market;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * How a match's markets are grouped into tabs, shared by the match page and the API.
 */
class MarketTabs
{
    /**
     * Tabs and the market types each one shows, in display order.
     *
     * @var array<string, array{label: string, types: list<MarketType>}>
     */
    public const TABS = [
        'main' => ['label' => 'Main', 'types' => [MarketType::MatchResult, MarketType::DoubleChance, MarketType::DrawNoBet]],
        'goals' => ['label' => 'Goals', 'types' => [MarketType::Totals, MarketType::BothTeamsToScore]],
        'handicap' => ['label' => 'Handicap', 'types' => [MarketType::Spreads]],
        'correct-score' => ['label' => 'Correct Score', 'types' => [MarketType::CorrectScore]],
        'half-time' => ['label' => 'Half-time', 'types' => [MarketType::HalfTimeFullTime]],
    ];

    /**
     * Markets per tab, sorted by type then line. Markets without selections and empty tabs are left out.
     *
     * @param  Collection<int, Market>  $markets  With selections loaded.
     * @return BaseCollection<string, Collection<int, Market>>
     */
    public static function group(Collection $markets): BaseCollection
    {
        return collect(self::TABS)
            ->map(fn (array $tab) => $markets
                ->filter(fn (Market $market): bool => in_array($market->type, $tab['types'], true) && $market->selections->isNotEmpty())
                // By type, then line from lowest to highest (handicaps go negative: -1.5 before -0.5 before +0.5).
                ->sortBy(fn (Market $market): string => sprintf('%02d-%09.2f', array_search($market->type, $tab['types'], true), (float) $market->line + 1000))
                ->values())
            ->filter(fn (Collection $markets): bool => $markets->isNotEmpty());
    }

    public static function label(string $key): string
    {
        return __(self::TABS[$key]['label'] ?? $key);
    }
}
