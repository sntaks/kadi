<?php

namespace App\Games;

use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Support\Money;
use App\Support\SportsPages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * The landing hero's slides (config/hero.php). The lead slide (the Kadi table and the big match) is always first;
 * every other slide is kept only while what it advertises is live: its switches are on, its CTA route exists, the
 * catalogue offers its games, or a jackpot is taking entries. Figures come only from config copy or real records.
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

    public function __construct(private GameCatalogue $catalogue) {}

    /**
     * @return Collection<int, HeroSlide>
     */
    public function forLanding(): Collection
    {
        /** @var list<array<string, mixed>> $config */
        $config = config('hero.slides', []);
        $lead = collect($config)->firstWhere('kind', 'lead') ?? ['key' => 'lead', 'kind' => 'lead', 'label' => 'Kadi', 'title' => 'Kadi'];

        $others = collect($config)
            ->reject(fn (array $slide) => ($slide['kind'] ?? null) === 'lead')
            ->map(fn (array $slide) => $this->resolve($slide))
            ->filter();

        return collect([$this->make($lead)])
            ->concat($others)
            ->take(max(1, (int) config('hero.max_slides', 6)))
            ->values();
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
                $game = GameArtwork::gameByPrefix($code) ?? 'slots';

                if ($path = GameArtwork::source($game, $code)) {
                    $sources[$path] = 'card';
                }
            }
        }

        return $sources;
    }

    /** @param  array<string, mixed>  $slide */
    private function resolve(array $slide): ?HeroSlide
    {
        if (! $this->passes($slide['requires'] ?? [])) {
            return null;
        }

        if (isset($slide['cta']['route']) && ! Route::has($slide['cta']['route'])) {
            return null;
        }

        return match ($slide['kind'] ?? null) {
            'games' => $this->games($slide),
            'jackpot' => $this->jackpot($slide),
            'promo', 'install' => str_contains((string) $slide['title'], ':amount') && config('kadi.promotions.signup_bonus_amount') === null
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
            ->map(fn (GameTitle $title) => GameArtwork::source($title->game, $title->code))
            ->filter()
            ->map(fn (string $path) => $this->image($path, 'card'))
            ->values()
            ->all();

        return $this->make($slide, [], $art, ['titles' => $titles->all()]);
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
     * @param  list<array{src: string, srcset: string, w: int, h: int, role: string}>|null  $art
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
            ctaUrl: isset($slide['cta']['route']) ? route($slide['cta']['route']) : null,
            art: $art ?? array_map(fn (array $a) => $this->image($a['from'], $a['role']), $slide['art'] ?? []),
            transition: (string) ($slide['transition'] ?? 'fade-zoom'),
            duration: (int) ($slide['duration'] ?? config('hero.durations.'.($kind === 'lead' ? 'lead' : 'default'), 7000)),
            extra: $extra,
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
