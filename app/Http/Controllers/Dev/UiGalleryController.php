<?php

namespace App\Http\Controllers\Dev;

use App\Games\GameArtwork;
use App\Games\GameCatalogue;
use App\Games\HeroSlide;
use App\Games\HeroSlides;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * /_ui: every Night table component in every state, for design work. Answers only
 * when `app.ui_gallery` is on (local by default). Uses sample titles built from
 * config, so it works without the Kadi API.
 */
class UiGalleryController extends Controller
{
    public function __invoke(GameCatalogue $catalogue): View
    {
        abort_unless(config('app.ui_gallery'), 404);

        $titles = collect([
            ['slots_dragon_fire_fortune', 'Dragon Fire Fortune'],
            ['slots_triple_jackpot_fruits', 'Triple Jackpot Fruits'],
            ['slots_mermaid_millions', 'Mermaid Millions'],
            ['slots_werewolf_moon_wilds', 'Werewolf Moon Wilds'],
            ['scratch_safari_lotto', 'Safari Lotto'],
            ['slots_brand_new', 'Brand New Title'], // not in config: the fallback tile
        ])->map(fn (array $p) => $catalogue->presentTitle(GameArtwork::gameByPrefix($p[0]) ?? 'slots', ['code' => $p[0], 'name' => $p[1]]));

        return view('dev.ui-gallery', ['titles' => $titles, 'heroSlides' => $this->heroSlides()]);
    }

    /**
     * Three sample hero slides from the built art (public/images/hero), so every layer and transition can be seen
     * without the API or a jackpot. Sample copy only: never shown outside /_ui.
     *
     * @return Collection<int, HeroSlide>
     */
    private function heroSlides(): Collection
    {
        $art = fn (string $from, string $role, array $more = []) => [
            'src' => asset(HeroSlides::builtPath($from, max(HeroSlides::widths($role)))),
            'srcset' => collect(HeroSlides::widths($role))->map(fn (int $w) => asset(HeroSlides::builtPath($from, $w))." {$w}w")->implode(', '),
            'w' => max(HeroSlides::widths($role)), 'h' => max(HeroSlides::widths($role)), 'role' => $role, ...$more,
        ];

        return collect([
            new HeroSlide('kadi', 'kadi', 'Kadi table', null, 'Kadi tables are open', null, null, null, [], 'curtain', 9000),
            new HeroSlide('sample-promo', 'promo', 'Promo slide', 'Sample eyebrow', 'A promotion headline', 'Sample copy for a promotion slide.',
                'Call to action', '#', [$art('images/casino/crown.png', 'object')], 'iris', 7000),
            new HeroSlide('sample-bg', 'jackpot', 'Background slide', 'Sample eyebrow', 'A slide over a background image', 'Sample copy.',
                'Call to action', '#', [$art('images/og-sports.jpg', 'bg')], 'parallax', 7000),
        ]);
    }
}
