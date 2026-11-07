<?php

namespace App\Console\Commands;

use App\Games\GameArtwork;
use App\Games\GameCatalogue;
use App\Games\GameTitle;
use Illuminate\Console\Command;

class GamesListCommand extends Command
{
    protected $signature = 'games:list
        {--refresh : Ask the Kadi API instead of using the cached catalogue}';

    protected $description = 'Show the lobby: games and titles from the Kadi API joined with config/games.php';

    public function handle(GameCatalogue $catalogue): int
    {
        if ($this->option('refresh')) {
            $catalogue->refresh();
        }

        $games = $catalogue->games();

        if ($games->isEmpty()) {
            $this->warn('The catalogue is empty: the Kadi API is unreachable and nothing is cached.');

            return self::FAILURE;
        }

        foreach ($games as $game) {
            $this->line(sprintf('<info>%s</info> %s · %s · %d products', $game->code, $game->name, $game->launchUrl, count($game->products)));
        }

        $titles = $catalogue->titles();

        $this->table(
            ['Product', 'Name', 'Collection', 'Badges', 'Thumbnail'],
            $titles->map(fn (GameTitle $title) => [
                $title->code,
                $title->name,
                $title->collection ?? '-',
                implode(', ', array_merge($title->badges, $title->featured ? ['featured'] : [])) ?: '-',
                $title->thumbnail ? 'yes' : 'MISSING (run games:artwork)',
            ])->all(),
        );

        $listed = $titles->pluck('key')->all();
        $configured = array_keys(GameArtwork::configuredProducts());
        $unpresented = array_values(array_diff($listed, $configured));
        $notListed = array_values(array_diff($configured, $listed));

        if ($unpresented !== []) {
            $this->warn('Shown as fallback tiles (not in config/games.php): '.implode(', ', $unpresented));
        }

        if ($notListed !== []) {
            $this->warn('In config/games.php but not offered by the API (hidden): '.implode(', ', $notListed));
        }

        return self::SUCCESS;
    }
}
