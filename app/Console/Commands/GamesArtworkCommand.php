<?php

namespace App\Console\Commands;

use App\Games\GameArtwork;
use App\Games\GameCatalogue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class GamesArtworkCommand extends Command
{
    protected $signature = 'games:artwork
        {--force : Rebuild thumbnails that are already up to date}
        {--only=* : Only these products (keys: the code without its slots_/scratch_ prefix)}
        {--with-api : Also build titles the Kadi API lists that config/games.php does not know}';

    protected $description = 'Build the lobby thumbnails (small WebP) from the original game artwork';

    public function handle(GameCatalogue $catalogue): int
    {
        $products = GameArtwork::configuredProducts();

        // Config only by default, so the build never depends on the network. With --with-api, titles
        // the API lists but config does not know yet also get a thumbnail when an original exists
        // under the usual naming.
        if ($this->option('with-api')) {
            foreach ($catalogue->titles() as $title) {
                $products[$title->key] ??= $title->game;
            }
        }

        if ($only = $this->option('only')) {
            $products = array_intersect_key($products, array_flip($only));
        }

        $width = (int) config('games.artwork.width');
        $height = (int) config('games.artwork.height');
        $quality = (int) config('games.artwork.quality');
        $manager = ImageManager::usingDriver(Driver::class);
        File::ensureDirectoryExists(public_path(trim((string) config('games.artwork.thumbnails'), '/')));

        $built = $skipped = 0;
        $missing = [];

        foreach ($products as $code => $game) {
            $source = GameArtwork::source($game, $code);

            if ($source === null || ! is_file(public_path($source))) {
                $missing[] = $code;

                continue;
            }

            $target = public_path(GameArtwork::thumbnail($code));

            if (! $this->option('force') && is_file($target) && filemtime($target) >= filemtime(public_path($source))) {
                $skipped++;

                continue;
            }

            try {
                $manager->decode(public_path($source))->cover(width: $width, height: $height)->save($target, quality: $quality);
                $built++;
            } catch (Throwable $e) {
                $this->error("{$code}: ".$e->getMessage());
                $missing[] = $code;
            }
        }

        $this->info("Built {$built}, up to date {$skipped}.");

        if ($missing !== []) {
            $this->warn('No usable artwork for: '.implode(', ', $missing));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
