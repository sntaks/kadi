<?php

namespace App\Livewire\Games;

use App\Games\Game;
use App\Games\GameCatalogue;
use App\Games\GameLauncher;
use App\Games\GameTitle;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The in-site game page: the site's menu and wallet stay, the game loads in a frame
 * (plan §4a). The page itself never holds a launch token: its form posts to
 * POST /play/{game} *inside the frame*, which hands the player on to the game.
 */
class Play extends Component
{
    public string $game;

    public ?string $title = null;

    public function mount(GameLauncher $launcher, string $game, ?string $title = null): void
    {
        $found = $launcher->game($game);
        abort_if($found === null, 404);

        // A game with titles opens on one; a single-tile game has no title segment.
        if ($found->hasTitles()) {
            abort_if($title === null || $found->titleBySlug($title) === null, 404);
        } else {
            abort_if($title !== null, 404);
        }

        $this->game = $found->code;
        $this->title = $title;
    }

    public function render(GameLauncher $launcher, GameCatalogue $catalogue): View
    {
        // Looked up again on purpose: only codes survive between requests, and the catalogue decides.
        $game = $launcher->game($this->game);
        $title = $this->title !== null ? $game?->titleBySlug($this->title) : null;
        abort_if($game === null || ($this->title !== null && $title === null), 404);

        $name = $title->name ?? $game->name;

        return view('livewire.games.play', [
            'gameModel' => $game,
            'titleModel' => $title,
            'name' => $name,
            'crumb' => $this->crumb($game, $title),
            'more' => $this->moreLikeThis($game, $title, $catalogue),
            'origins' => $this->allowedOrigins($catalogue),
        ])->layout('layouts.site', ['shell' => 'app'])->title($name.' · Kadi');
    }

    private function crumb(Game $game, ?GameTitle $title): string
    {
        if ($title === null) {
            return $game->name;
        }

        $collection = $title->collection ? config("games.collections.{$title->collection}.label") : null;

        return implode(' · ', array_filter(['Casino', $game->name, $collection]));
    }

    /** @return list<GameTitle> */
    private function moreLikeThis(Game $game, ?GameTitle $title, GameCatalogue $catalogue): array
    {
        // Same collection first, then the rest of the game; Kadi suggests the featured slots.
        $pool = $title === null
            ? $catalogue->featured()
            : ($title->collection ? $catalogue->collection($title->collection) : collect())->concat($game->titles);

        return $pool->reject(fn (GameTitle $t) => $t->code === $title?->code)->unique('code')->take(8)->values()->all();
    }

    /**
     * Origins allowed to message this page (`ready`, `balance`, `close`): the game servers only.
     *
     * @return list<string>
     */
    private function allowedOrigins(GameCatalogue $catalogue): array
    {
        $urls = $catalogue->games()->map(fn (Game $g) => $g->launchUrl)->push((string) config('services.kadi_api.play_url'));

        return $urls
            ->filter(fn (string $url) => GameCatalogue::isAllowedLaunchUrl($url))
            ->map(fn (string $url) => 'https://'.strtolower((string) parse_url($url, PHP_URL_HOST)).(parse_url($url, PHP_URL_PORT) ? ':'.parse_url($url, PHP_URL_PORT) : ''))
            ->unique()
            ->values()
            ->all();
    }
}
