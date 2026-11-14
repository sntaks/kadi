<?php

namespace App\Sportsbook\OddsApi;

/**
 * Where odds are taken from: whole regions (`regions=uk`) or up to a few named bookmakers (`bookmakers=pinnacle,...`).
 * Named bookmakers replace the regions when set. The API charges each group of 10 bookmakers like one region.
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

    public static function fromConfig(): self
    {
        return new self(
            regions: self::list((string) config('sportsbook.odds_api.region')),
            bookmakers: self::list((string) config('sportsbook.odds_api.bookmakers')),
        );
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
