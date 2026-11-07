<?php

namespace App\Games;

/**
 * A game from the Kadi API catalogue (kadi, slots, scratch...). Games with
 * `titles` (config/games.php) open on a chosen title; the others are one tile.
 */
final readonly class Game
{
    /**
     * @param  list<string>  $products  every active product code, in API order
     * @param  list<GameTitle>  $titles  lobby tiles (empty for single-tile games)
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $launchUrl,
        public int $sortOrder,
        public ?string $tagline,
        public array $products,
        public array $titles,
    ) {}

    public function hasTitles(): bool
    {
        return $this->titles !== [];
    }

    public function titleBySlug(string $slug): ?GameTitle
    {
        foreach ($this->titles as $title) {
            if ($title->slug() === $slug) {
                return $title;
            }
        }

        return null;
    }

    /** Plays inside the site's frame (config `games.games.{code}.frame`, default true). */
    public function playsInFrame(): bool
    {
        return self::framed($this->code);
    }

    /** The same rule without a catalogue lookup, for buttons rendered on every page. */
    public static function framed(string $code): bool
    {
        return (bool) config("games.games.{$code}.frame", true);
    }

    /** Launched with Kadi's own `ggid` form instead of an API launch token. */
    public function usesGgid(): bool
    {
        return config("games.games.{$this->code}.launch") === 'ggid';
    }

    /** By the API's product code or the title's key (a play page opened before the API renamed its codes still launches). */
    public function title(string $code): ?GameTitle
    {
        foreach ($this->titles as $title) {
            if ($title->is($code)) {
                return $title;
            }
        }

        return null;
    }
}
