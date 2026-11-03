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

        if (isset($config['artwork'], $config['prefix']) && str_starts_with($product, $config['prefix'])) {
            return str_replace('{name}', substr($product, strlen($config['prefix'])), $config['artwork']);
        }

        return null;
    }

    /**
     * Every product config/games.php presents, mapped to its game: the slots in
     * collections and the explicitly mapped titles.
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
                $products[$code] ??= self::gameByPrefix($code) ?? 'slots';
            }
        }

        ksort($products);

        return $products;
    }

    /** The game whose `prefix` the product code starts with. */
    public static function gameByPrefix(string $product): ?string
    {
        foreach (config('games.games', []) as $game => $config) {
            if (isset($config['prefix']) && str_starts_with($product, $config['prefix'])) {
                return $game;
            }
        }

        return null;
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
