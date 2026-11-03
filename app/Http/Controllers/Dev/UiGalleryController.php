<?php

namespace App\Http\Controllers\Dev;

use App\Games\GameArtwork;
use App\Games\GameCatalogue;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

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

        return view('dev.ui-gallery', ['titles' => $titles]);
    }
}
