<?php

namespace App\Sportsbook\Support;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Sport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * How one sport is priced, settled and shown, from `config('sportsbook.sports')`. Football has a 1X2 with a draw
 * and derives its other markets from it; a sport without a draw has a two-way Winner (incl. overtime) and only
 * the markets the feed sells. A sport missing from config is treated like football, as everything was before.
 *
 * A two-way sport can still have competitions whose match result has a draw (Test cricket, rugby union): those are
 * listed in `draw_competitions` and only `forCompetition()` answers for them.
 */
final class SportProfile
{
    /**
     * @param  list<MarketType>  $markets  feed markets bought for this sport's competitions
     * @param  list<string>  $popular  feed competition keys listed first
     * @param  list<string>  $drawCompetitions  feed competition keys (or `prefix_*`) whose match result has a draw
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $icon,
        public readonly int $sortOrder,
        public readonly string $keyPrefix,
        public readonly bool $hasDraw,
        public readonly bool $derivedMarkets,
        public readonly array $markets,
        public readonly array $popular,
        public readonly int $maxScore = 99,
        public readonly string $totalsLabel = 'Total goals',
        public readonly array $drawCompetitions = [],
        public readonly bool $autoSettle = true,
    ) {}

    /** @return array<string, self> by slug, in menu order */
    public static function all(): array
    {
        $profiles = [];

        foreach ((array) config('sportsbook.sports', []) as $slug => $config) {
            $profiles[$slug] = self::fromConfig((string) $slug, (array) $config);
        }

        uasort($profiles, fn (self $a, self $b) => $a->sortOrder <=> $b->sortOrder);

        return $profiles;
    }

    public static function find(string $slug): ?self
    {
        return self::all()[$slug] ?? null;
    }

    /** A sport's profile; one not in config is football's, as every sport was before. */
    public static function forSport(?Sport $sport): self
    {
        return ($sport !== null ? self::find($sport->slug) : null) ?? self::find('football') ?? self::fallback($sport);
    }

    /**
     * Narrows a competition query to one sport. Football also takes any sport missing from config (see forSport).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $competitions
     * @return Builder<TModel>
     */
    public static function scopeCompetitions(Builder $competitions, string $slug): Builder
    {
        if ($slug !== 'football') {
            return $competitions->whereHas('sport', fn (Builder $sports) => $sports->where('slug', $slug));
        }

        $others = array_values(array_diff(array_keys(self::all()), ['football']));

        return $competitions->whereDoesntHave('sport', fn (Builder $sports) => $sports->whereIn('slug', $others));
    }

    /**
     * The profile that prices and settles one competition's matches: its sport's, with the draw switched on for a
     * competition listed in `draw_competitions` (Test cricket in a two-way sport).
     */
    public static function forCompetition(?Competition $competition): self
    {
        $profile = self::forSport($competition?->sport);

        return $competition !== null && ! $profile->hasDraw && $profile->drawIn((string) $competition->odds_api_key)
            ? $profile->withDraw()
            : $profile;
    }

    /** Whether some of this sport's competitions have a draw (the sport-wide list then needs a 1X2 column set). */
    public function hasDrawAnywhere(): bool
    {
        return $this->hasDraw || $this->drawCompetitions !== [];
    }

    /** Whether a feed competition key is listed in `draw_competitions` (an entry ending in `*` is a prefix). */
    public function drawIn(string $key): bool
    {
        foreach ($this->drawCompetitions as $pattern) {
            if ($pattern === $key || (str_ends_with($pattern, '*') && str_starts_with($key, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /** The sport a feed competition key (`basketball_nba`) belongs to. */
    public static function forCompetitionKey(string $key): ?self
    {
        foreach (self::all() as $profile) {
            if ($profile->keyPrefix !== '' && str_starts_with($key, $profile->keyPrefix)) {
                return $profile;
            }
        }

        return null;
    }

    /** @return list<string> the match-result outcomes, in display order */
    public function matchResultOutcomes(): array
    {
        return $this->hasDraw ? ['home', 'draw', 'away'] : ['home', 'away'];
    }

    public function matchResultLabel(): string
    {
        return $this->hasDraw ? __('1X2') : __('Winner');
    }

    /** @param  array<string, mixed>  $config */
    private static function fromConfig(string $slug, array $config): self
    {
        $markets = [];

        foreach (array_filter(array_map('trim', explode(',', (string) ($config['markets'] ?? 'h2h')))) as $key) {
            $type = MarketType::tryFrom($key);

            if ($type !== null && $type->isFeedMarket()) {
                $markets[] = $type;
            }
        }

        return new self(
            slug: $slug,
            name: (string) ($config['name'] ?? Str::headline($slug)),
            icon: (string) ($config['icon'] ?? 'ball'),
            sortOrder: (int) ($config['sort_order'] ?? 99),
            keyPrefix: (string) ($config['key_prefix'] ?? ''),
            hasDraw: (bool) ($config['has_draw'] ?? true),
            derivedMarkets: (bool) ($config['derived_markets'] ?? false),
            markets: $markets === [] ? [MarketType::MatchResult] : $markets,
            popular: array_values((array) ($config['popular'] ?? [])),
            maxScore: (int) ($config['max_score'] ?? 99),
            totalsLabel: (string) ($config['totals_label'] ?? 'Total goals'),
            drawCompetitions: array_values(array_map('strval', (array) ($config['draw_competitions'] ?? []))),
            autoSettle: (bool) ($config['auto_settle'] ?? true),
        );
    }

    private function withDraw(): self
    {
        return new self(
            slug: $this->slug,
            name: $this->name,
            icon: $this->icon,
            sortOrder: $this->sortOrder,
            keyPrefix: $this->keyPrefix,
            hasDraw: true,
            derivedMarkets: $this->derivedMarkets,
            markets: $this->markets,
            popular: $this->popular,
            maxScore: $this->maxScore,
            totalsLabel: $this->totalsLabel,
            drawCompetitions: $this->drawCompetitions,
            autoSettle: $this->autoSettle,
        );
    }

    private static function fallback(?Sport $sport): self
    {
        return new self(
            slug: $sport->slug ?? 'football',
            name: $sport->name ?? 'Football',
            icon: 'ball',
            sortOrder: $sport->sort_order ?? 99,
            keyPrefix: '',
            hasDraw: true,
            derivedMarkets: true,
            markets: [MarketType::MatchResult],
            popular: [],
        );
    }
}
