<?php

namespace App\Console\Commands;

use App\Games\HeroSlides;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class HeroArtworkCommand extends Command
{
    protected $signature = 'hero:artwork {--force : Rebuild images that are already up to date}';

    protected $description = 'Build the landing hero images (WebP, two widths) from the originals config/hero.php uses';

    /** Background layers sit under text: cropped to 16:9 and darkened. */
    private const BG_RATIO = 16 / 9;

    private const BG_DARKEN = -35;

    private const QUALITY = 72;

    public function handle(): int
    {
        $manager = ImageManager::usingDriver(Driver::class);
        File::ensureDirectoryExists(public_path('images/hero'));

        $built = $skipped = 0;
        $missing = [];

        foreach (HeroSlides::sources() as $source => $role) {
            if (! is_file(public_path($source))) {
                $missing[] = $source;

                continue;
            }

            foreach (HeroSlides::widths($role) as $width) {
                $target = public_path(HeroSlides::builtPath($source, $width));

                if (! $this->option('force') && is_file($target) && filemtime($target) >= filemtime(public_path($source))) {
                    $skipped++;

                    continue;
                }

                try {
                    $image = $manager->decode(public_path($source));

                    $image = $role === 'bg'
                        ? $image->cover(width: $width, height: (int) round($width / self::BG_RATIO))->brightness(self::BG_DARKEN)
                        : $image->scaleDown(width: $width);

                    $image->save($target, quality: self::QUALITY);
                    $built++;
                } catch (Throwable $e) {
                    $this->error("{$source}: ".$e->getMessage());
                    $missing[] = $source;
                }
            }
        }

        $this->info("Built {$built}, up to date {$skipped}.");

        if ($missing !== []) {
            $this->warn('No usable original for: '.implode(', ', array_unique($missing)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
