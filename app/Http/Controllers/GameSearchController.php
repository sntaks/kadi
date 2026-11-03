<?php

namespace App\Http\Controllers;

use App\Games\Game;
use App\Games\GameCatalogue;
use Illuminate\Http\JsonResponse;

/**
 * The top bar search index: every playable title with its play page. Fetched by the browser on the
 * first focus of the search box, so ordinary page views never read the catalogue for it.
 */
class GameSearchController extends Controller
{
    public function __invoke(GameCatalogue $catalogue): JsonResponse
    {
        $entries = [];

        foreach ($catalogue->games() as $game) {
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

        // The same for everyone; SetCacheHeaders lets guests' browsers keep it for five minutes,
        // as long as the catalogue's own cache.
        return response()->json(['data' => $entries]);
    }
}
