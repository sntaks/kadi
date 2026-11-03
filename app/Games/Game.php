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

    public function title(string $code): ?GameTitle
    {
        foreach ($this->titles as $title) {
            if ($title->code === $code) {
                return $title;
            }
        }

        return null;
    }
}
