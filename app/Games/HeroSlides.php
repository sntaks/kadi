<?php

namespace App\Games;

use App\Predict\Data\Market;
use App\Predict\Markets;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Support\Money;
use App\Support\PredictPages;
use App\Support\SportsPages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The landing hero's slides (config/hero.php). A slide is kept only while what it advertises is live: its switches
 * are on, its CTA route exists, the catalogue offers its games, a jackpot is taking entries, or there is a market to
 * show. With a seed the live slides are shuffled (one seed per visit, so Livewire re-renders keep the same slides),
 * then `max_slides` of them are taken; the big match is never first (on phones it sits under the carousel).
 * Figures come only from config copy or real records.
 */
class HeroSlides
{
    /** Widths `hero:artwork` builds per layer role (px, 1x and 2x of how wide the layer shows). */
    public const WIDTHS = ['bg' => [480, 960], 'object' => [400, 800], 'card' => [200, 400]];

    /** @return list<int> */
    public static function widths(string $role): array
    {
        return self::WIDTHS[$role] ?? self::WIDTHS['bg'];
    }

    public function __construct(private GameCatalogue $catalogue, private Markets $markets) {}

    /** @var list<Market>|null */
    private ?array $pickedMarkets = null;

    /**
     * @return Collection<int, HeroSlide>
     */
    public function forLanding(bool $bigMatch = false, ?int $seed = null): Collection
    {
        /** @var list<array<string, mixed>> $config */
        $config = config('hero.slides', []);

        $slides = collect($config)
            ->map(fn (array $slide) => $this->resolve($slide, $bigMatch))
            ->filter()
            ->values();

        // Today's big match is always kept (on phones it is the match card under the carousel, not a slide) and never
        // first: the page must not open on a slide phones take out of the rotation.
        $max = max(1, (int) config('hero.max_slides', 6));
        $match = $slides->firstWhere('kind', 'match');
        $others = $slides->reject(fn (HeroSlide $slide) => $slide === $match)->values();
        $random = $seed === null ? null : new Randomizer(new Mt19937($seed)); // the same seed, the same slides on re-renders

        if ($random !== null) {
            $others = collect($random->shuffleArray($others->all()));
        }

        $slides = $others->take($match ? $max - 1 : $max)->values();

        if ($match !== null) {
            $at = $slides->isEmpty() ? 0 : ($random?->getInt(1, $slides->count()) ?? 1);
            $slides->splice($at, 0, [$match]);
        }

        return $slides;
    }

    /**
     * The markets the two market slides show: the ids staff pinned (config hero.markets), then the trending Local
     * markets, the preferred categories (politics) first, one per event. Only markets taking bets with both sides
     * priced. Read from the cache only (Markets::warmLobby() keeps it warm): the landing never waits on the API.
     *
     * @return list<Market>
     */
    public function markets(): array
    {
        if ($this->pickedMarkets !== null) {
            return $this->pickedMarkets;
        }

        $now = Date::now();
        $usable = fn (?Market $market) => $market !== null && ! $market->isImported()
            && $market->sideAvailable('yes', $now) && $market->sideAvailable('no', $now)
            && ! $market->odds?->paysBelowStake('yes') && ! $market->odds?->paysBelowStake('no');

        $pinned = collect((array) config('hero.markets', []))
            ->map(fn ($id) => $this->markets->find((int) $id, cachedOnly: true))
            ->filter($usable);

        $preferred = (array) config('hero.market_categories', []);
        $trending = collect($this->markets->list(['source' => 'custom', 'sort' => 'trending'], cachedOnly: true)['markets'])
            ->filter($usable)
            ->sortBy(fn (Market $market) => ($i = array_search($market->category, $preferred, true)) === false ? count($preferred) : $i);

        $picked = [];
        $events = [];

        foreach ($pinned->concat($trending) as $market) {
            $event = $market->eventTitle ?? 'market-'.$market->id;

            if (isset($picked[$market->id]) || isset($events[$event])) {
                continue;
            }

            $picked[$market->id] = $market;
            $events[$event] = true;

            if (count($picked) === 2) {
                break;
            }
        }

        return $this->pickedMarkets = array_values($picked);
    }

    /** The built file for an original under public/, e.g. images/casino/crown.png → images/hero/crown-960.webp. */
    public static function builtPath(string $from, int $width): string
    {
        return 'images/hero/'.pathinfo($from, PATHINFO_FILENAME).'-'.$width.'.webp';
    }

    /**
     * Every original the configured slides use, with its role: art sources and the games slides' title artwork.
     *
     * @return array<string, string> path under public/ => role
     */
    public static function sources(): array
    {
        $sources = [];

        foreach (config('hero.slides', []) as $slide) {
            foreach ($slide['art'] ?? [] as $art) {
                $sources[$art['from']] = $art['role'];
            }

            foreach ($slide['titles'] ?? [] as $code) {
                $game = GameArtwork::gameOf($code) ?? 'slots';

                if ($path = GameArtwork::source($game, GameTitle::keyFor($game, $code))) {
                    $sources[$path] = 'card';
                }
            }
        }

        return $sources;
    }

    /** @param  array<string, mixed>  $slide */
    private function resolve(array $slide, bool $bigMatch): ?HeroSlide
    {
        if (! $this->passes($slide['requires'] ?? [])) {
            return null;
        }

        if (isset($slide['cta']['route']) && ! Route::has($slide['cta']['route'])) {
            return null;
        }

        return match ($slide['kind'] ?? null) {
            'match' => $bigMatch ? $this->make($slide) : null,
            'kadi' => $this->make($slide),
            'games' => $this->games($slide),
            'casino' => $this->casino($slide),
            'tiers' => $this->tiers($slide),
            'market' => $this->market($slide),
            'jackpot' => $this->jackpot($slide),
            'promo' => str_contains((string) $slide['title'], ':amount') && config('kadi.promotions.signup_bonus_amount') === null
                ? null
                : $this->make($slide, ['amount' => (string) config('kadi.promotions.signup_bonus_amount')]),
            default => null,
        };
    }

    /** @param  list<string>  $requires */
    private function passes(array $requires): bool
    {
        foreach ($requires as $gate) {
            $on = match ($gate) {
                'promotions' => (bool) config('kadi.promotions.enabled'),
                'referrals' => (bool) config('kadi.referrals.enabled'),
                'sports' => SportsPages::on(),
                'token_launch' => (bool) config('platform.token_launch'),
                'predict' => PredictPages::on(),
                default => false,
            };

            if (! $on) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $slide */
    private function games(array $slide): ?HeroSlide
    {
        $titles = collect($slide['titles'] ?? [])
            ->map(fn (string $code) => $this->catalogue->title($code))
            ->filter()
            ->values();

        if ($titles->isEmpty()) {
            return null;
        }

        $art = $titles
            ->filter(fn (GameTitle $title) => GameArtwork::source($title->game, $title->key) !== null)
            ->map(fn (GameTitle $title) => [
                ...$this->image((string) GameArtwork::source($title->game, $title->key), 'card'),
                'alt' => $title->name,
                'href' => $title->pageUrl(),
            ])
            ->values()
            ->all();

        return $this->make($slide, [], $art, ['titles' => $titles->all()]);
    }

    /**
     * One casino game (scratch cards, Coin Flip, Roulette): a fan of up to three of its titles the catalogue offers,
     * and its configured figures (`:prize`, `:multiplier`). Dropped when the catalogue offers none of its titles or a
     * figure the copy needs is not configured.
     *
     * @param  array<string, mixed>  $slide
     */
    private function casino(array $slide): ?HeroSlide
    {
        $game = (string) ($slide['game'] ?? '');
        $titles = $this->titlesOf($game)->take(3);
        $replace = $this->figures($slide, $game);

        if ($titles->isEmpty() || $replace === null) {
            return null;
        }

        $art = $titles
            ->filter(fn (GameTitle $title) => $title->thumbnail !== null)
            ->map(fn (GameTitle $title) => [
                'src' => (string) $title->thumbnail, 'srcset' => $title->thumbnail.' 320w', 'w' => 320, 'h' => 480, 'role' => 'card',
                'alt' => $title->name, 'href' => $title->pageUrl(),
            ])
            ->values()
            ->all();

        return $this->make($slide, $replace, $art, ['titles' => $titles->all()]);
    }

    /**
     * A game's jackpot tiers as a prize table (games.games.{game}.jackpots), while the catalogue offers that game.
     *
     * @param  array<string, mixed>  $slide
     */
    private function tiers(array $slide): ?HeroSlide
    {
        $game = (string) ($slide['game'] ?? '');
        $tiers = collect((array) config("games.games.{$game}.jackpots", []))
            ->filter(fn ($tier) => is_array($tier) && isset($tier['name']) && (int) ($tier['amount'] ?? 0) > 0)
            ->values();

        if ($tiers->isEmpty() || $this->titlesOf($game)->isEmpty()) {
            return null;
        }

        return $this->make($slide, [], null, ['tiers' => $tiers->all()]);
    }

    /** @param  array<string, mixed>  $slide */
    private function market(array $slide): ?HeroSlide
    {
        $market = $this->markets()[(int) ($slide['slot'] ?? 0)] ?? null;

        if ($market === null) {
            return null;
        }

        // A market that is one choice of an event ("Who will win…?", one market per candidate) shows the whole event:
        // its question as the headline and every choice on the slide.
        $choices = $this->eventChoices($market);

        return $choices === []
            ? $this->make($slide, ['question' => $market->question], null, ['market' => $market])
            : $this->make($slide, ['question' => (string) $market->eventTitle], null, ['market' => $market, 'choices' => $choices]);
    }

    /**
     * Every open choice of the market's event, likeliest first, from the cached Local lists only (its category's, all
     * Local, trending Local: the ones Markets::warmLobby() keeps warm). Empty for a market without an event, or when
     * fewer than two choices are cached.
     *
     * @return list<Market>
     */
    private function eventChoices(Market $market): array
    {
        if ($market->eventTitle === null) {
            return [];
        }

        $now = Date::now();
        $lists = [['source' => 'custom', 'category' => $market->category], ['source' => 'custom'], ['source' => 'custom', 'sort' => 'trending']];

        $choices = collect($lists)
            ->flatMap(fn (array $filters) => $this->markets->list(array_filter($filters), cachedOnly: true)['markets'])
            ->filter(fn (Market $choice) => $choice->eventTitle === $market->eventTitle && $choice->sideAvailable('yes', $now) && ! $choice->odds?->paysBelowStake('yes'))
            ->unique(fn (Market $choice) => $choice->id)
            ->sortByDesc(fn (Market $choice) => $choice->odds->yesProbability ?? -1)
            ->values();

        return $choices->count() >= 2 ? $choices->all() : [];
    }

    /** @return Collection<int, GameTitle> */
    private function titlesOf(string $game): Collection
    {
        return $this->catalogue->titles()->filter(fn (GameTitle $title) => $title->game === $game)->values();
    }

    /**
     * The figures a casino slide's copy uses, from config only. Null when the copy needs one that is not set.
     *
     * @param  array<string, mixed>  $slide
     * @return array<string, string>|null
     */
    private function figures(array $slide, string $game): ?array
    {
        $text = ($slide['title'] ?? '').' '.($slide['eyebrow'] ?? '').' '.($slide['copy'] ?? '');
        $replace = [];

        foreach (['prize' => 'hero_prize', 'multiplier' => 'max_multiplier'] as $placeholder => $key) {
            if (! str_contains($text, ':'.$placeholder)) {
                continue;
            }

            $value = (int) config("games.games.{$game}.{$key}", 0);

            if ($value <= 0) {
                return null;
            }

            $replace[$placeholder] = number_format($value);
        }

        return $replace;
    }

    /** @param  array<string, mixed>  $slide */
    private function jackpot(array $slide): ?HeroSlide
    {
        $jackpot = Jackpot::query()->current()->first();

        if (! $jackpot instanceof Jackpot || ! $jackpot->isTakingEntries() || $jackpot->totalPrizes() <= 0) {
            return null;
        }

        $closesAt = $jackpot->closesAt();

        return $this->make($slide, [
            'prize' => Money::format($jackpot->totalPrizes()),
            'closes' => $closesAt?->timezone(config('sportsbook.timezone'))->format('D j M, H:i') ?? '',
        ], null, ['prize' => $jackpot->totalPrizes(), 'closesAt' => $closesAt]);
    }

    /**
     * @param  array<string, mixed>  $slide
     * @param  array<string, string>  $replace
     * @param  list<array<string, mixed>>|null  $art
     * @param  array<string, mixed>  $extra
     */
    private function make(array $slide, array $replace = [], ?array $art = null, array $extra = []): HeroSlide
    {
        $fill = fn (?string $text) => $text === null ? null : strtr($text, collect($replace)->mapWithKeys(fn ($v, $k) => [":{$k}" => $v])->all());
        $kind = (string) $slide['kind'];

        return new HeroSlide(
            key: (string) $slide['key'],
            kind: $kind,
            label: (string) $slide['label'],
            eyebrow: $fill($slide['eyebrow'] ?? null),
            title: (string) $fill((string) $slide['title']),
            copy: $fill($slide['copy'] ?? null),
            ctaLabel: $slide['cta']['label'] ?? null,
            ctaUrl: isset($slide['cta']['route']) ? route($slide['cta']['route'], $slide['cta']['params'] ?? []) : null,
            art: $art ?? array_map(fn (array $a) => $this->image($a['from'], $a['role']), $slide['art'] ?? []),
            transition: (string) ($slide['transition'] ?? 'fade-zoom'),
            duration: (int) ($slide['duration'] ?? config('hero.durations.default', 7000)),
            extra: [...$extra, 'needsAccount' => (bool) ($slide['cta']['account'] ?? false)],
        );
    }

    /** @return array{src: string, srcset: string, w: int, h: int, role: string} */
    private function image(string $from, string $role): array
    {
        $widths = self::widths($role);
        $largest = self::builtPath($from, max($widths));
        $size = is_file(public_path($largest)) ? @getimagesize(public_path($largest)) : false;

        return [
            'src' => asset($largest),
            'srcset' => collect($widths)->map(fn (int $w) => asset(self::builtPath($from, $w))." {$w}w")->implode(', '),
            'w' => $size ? $size[0] : max($widths),
            'h' => $size ? $size[1] : max($widths),
            'role' => $role,
        ];
    }
}
