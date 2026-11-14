<?php

namespace App\Sportsbook\OddsApi;

/**
 * Where odds are taken from: whole regions (`regions=uk`) or a few named bookmakers (`bookmakers=pinnacle,...`).
 * Named bookmakers replace the regions when set. The API charges each group of 10 bookmakers like one region.
 *
 * A market can have its own bookmakers (`sportsbook.odds_api.market_bookmakers`), e.g. football handicaps, which the
 * UK region hardly prices: such markets are bought in a call of their own, which costs the same per market. A
 * competition can have its own region (`sportsbook.odds_api.competition_regions`), e.g. Australia's NBL, which UK
 * books hardly price; a market's own bookmakers still come first.
 */
final class OddsApiSource
{
    public const BOOKMAKERS_PER_REGION = 10;

    /**
     * @param  list<string>  $regions
     * @param  list<string>  $bookmakers
     */
    public function __construct(
        public readonly array $regions,
        public readonly array $bookmakers = [],
    ) {}

    /**
     * The source for one market of one competition: the market's own bookmakers, else the competition's own region,
     * else the default source.
     */
    public static function fromConfig(?string $market = null, ?string $competition = null): self
    {
        $regions = self::list((string) config('sportsbook.odds_api.region'));
        $own = $market !== null ? self::list((string) (config("sportsbook.odds_api.market_bookmakers.{$market}") ?? '')) : [];

        if ($own !== []) {
            return new self(regions: $regions, bookmakers: $own);
        }

        $competitionRegions = $competition !== null ? self::list((string) (config('sportsbook.odds_api.competition_regions')[$competition] ?? '')) : [];

        if ($competitionRegions !== []) {
            return new self(regions: $competitionRegions);
        }

        return new self(regions: $regions, bookmakers: self::list((string) config('sportsbook.odds_api.bookmakers')));
    }

    /** The books a match's extra markets are bought from (`sportsbook.per_event.bookmakers`), else the default source. */
    public static function forEventOdds(): self
    {
        $own = self::list((string) config('sportsbook.per_event.bookmakers'));

        return $own !== [] ? new self(regions: self::list((string) config('sportsbook.odds_api.region')), bookmakers: $own) : self::fromConfig();
    }

    /**
     * Markets grouped by the source they are bought from, one group per API call, default source first.
     *
     * @param  list<string>  $markets
     * @return list<array{source: self, markets: list<string>}>
     */
    public static function groupMarkets(array $markets, ?string $competition = null): array
    {
        $default = self::fromConfig(competition: $competition);
        $groups = [json_encode($default->query()) => ['source' => $default, 'markets' => []]];

        foreach ($markets as $market) {
            $source = self::fromConfig($market, $competition);
            $key = json_encode($source->query());
            $groups[$key] ??= ['source' => $source, 'markets' => []];
            $groups[$key]['markets'][] = $market;
        }

        return array_values(array_filter($groups, fn (array $group): bool => $group['markets'] !== []));
    }

    /**
     * What buying these markets costs in credits, each from its own source.
     *
     * @param  list<string>  $markets
     */
    public static function creditsFor(array $markets, ?string $competition = null): int
    {
        return array_sum(array_map(fn (string $market): int => self::fromConfig($market, $competition)->creditsPerMarket(), $markets));
    }

    /**
     * The query parameter that picks the books.
     *
     * @return array{regions: string}|array{bookmakers: string}
     */
    public function query(): array
    {
        return $this->bookmakers !== []
            ? ['bookmakers' => implode(',', $this->bookmakers)]
            : ['regions' => implode(',', $this->regions)];
    }

    /** What one market costs in credits from this source. */
    public function creditsPerMarket(): int
    {
        return $this->bookmakers !== []
            ? (int) ceil(count($this->bookmakers) / self::BOOKMAKERS_PER_REGION)
            : max(1, count($this->regions));
    }

    /**
     * @return list<string>
     */
    private static function list(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== '')));
    }
}
