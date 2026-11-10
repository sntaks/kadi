<?php

namespace App\Sportsbook\Support;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Sport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * How one sport is priced, settled and shown, from `config('sportsbook.sports')`. Football has a 1X2 with a draw
 * and derives its other markets from it; a sport without a draw has a two-way Winner (incl. overtime) and only
 * the markets the feed sells. A sport missing from config is treated like football, as everything was before.
 */
final class SportProfile
{
    /**
     * @param  list<MarketType>  $markets  feed markets bought for this sport's competitions
     * @param  list<string>  $popular  feed competition keys listed first
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
