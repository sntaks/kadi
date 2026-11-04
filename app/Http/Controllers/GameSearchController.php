<?php

namespace App\Http\Controllers;

use App\Games\Game;
use App\Games\GameCatalogue;
use App\Sportsbook\Catalogue\SportsLobby;
use App\Support\SportsPages;
use Illuminate\Http\JsonResponse;

/**
 * The top bar search index: every playable title with its play page. Fetched by the browser on the
 * first focus of the search box, so ordinary page views never read the catalogue for it.
 */
class GameSearchController extends Controller
{
    public function __invoke(GameCatalogue $catalogue, SportsLobby $sports): JsonResponse
    {
        $sportsOn = SportsPages::on();
        abort_unless(config('platform.token_launch') || $sportsOn, 404);

        $entries = [];

        foreach (config('platform.token_launch') ? $catalogue->games() : [] as $game) {
            if ($game->hasTitles()) {
                foreach ($game->titles as $title) {
                    $entries[] = [
                        'name' => $title->name,
                        'kind' => $game->name,
                        'url' => route('play', ['game' => $game->code, 'title' => $title->slug()]),
                        'thumb' => $title->thumbnail,
                    ];
                }
            } elseif (Game::framed($game->code)) {
                $entries[] = ['name' => $game->name, 'kind' => 'Game', 'url' => route('play', ['game' => $game->code]), 'thumb' => null];
            }
        }

        if ($sportsOn) {
            array_push($entries, ...$sports->searchEntries());
        }

        // The same for everyone; SetCacheHeaders lets guests' browsers keep it for five minutes,
        // as long as the catalogue's own cache.
        return response()->json(['data' => $entries]);
    }
}
