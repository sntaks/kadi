<?php

namespace App\Games;

/**
 * Where a product's artwork lives: the original under public/ (from
 * config/games.php) and the lobby thumbnail that `games:artwork` builds.
 */
class GameArtwork
{
    /** Original image path relative to public/, or null when none is configured. */
    public static function source(string $game, string $product): ?string
    {
        $config = config("games.games.{$game}", []);

        if (isset($config['artwork_map'][$product])) {
            return $config['artwork_map'][$product];
        }

        if (isset($config['artwork'])) {
            return str_replace('{code}', $product, $config['artwork']);
        }

        return null;
    }

    /**
     * Every product config/games.php presents, mapped to its game: the explicitly
     * mapped titles and the slots in collections (collections hold slots only).
     *
     * @return array<string, string> product code => game code
     */
    public static function configuredProducts(): array
    {
        $products = [];

        foreach (config('games.games', []) as $game => $config) {
            foreach (array_keys($config['artwork_map'] ?? []) as $code) {
                $products[$code] = $game;
            }
        }

        foreach (config('games.collections', []) as $collection) {
            foreach ($collection['products'] ?? [] as $code) {
                $products[$code] ??= 'slots';
            }
        }

        ksort($products);

        return $products;
    }

    /** The game config/games.php files a product under, or null when config does not know it. */
    public static function gameOf(string $product): ?string
    {
        return self::configuredProducts()[$product] ?? null;
    }

    /** Thumbnail path relative to public/. */
    public static function thumbnail(string $product): string
    {
        return trim((string) config('games.artwork.thumbnails'), '/').'/'.$product.'.webp';
    }

    /** Public URL of the thumbnail, or null when it has not been built. */
    public static function thumbnailUrl(string $product): ?string
    {
        $path = self::thumbnail($product);

        return is_file(public_path($path)) ? asset($path) : null;
    }
}
