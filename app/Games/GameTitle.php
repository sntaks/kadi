<?php

namespace App\Games;

/**
 * One lobby tile inside a game: a slot or a scratch card. Name and status come
 * from the Kadi API; artwork, collection and badges from config/games.php.
 */
final readonly class GameTitle
{
    /**
     * @param  list<string>  $badges
     */
    public function __construct(
        public string $code,
        public string $game,
        public string $name,
        public ?string $thumbnail,
        public ?string $collection,
        public array $badges,
        public bool $featured,
        public bool $presented,
    ) {}

    /** False for a product the API lists but config/games.php does not know (a plain fallback tile). */
    public function isPresented(): bool
    {
        return $this->presented;
    }

    public function hasBadge(string $badge): bool
    {
        return in_array($badge, $this->badges, true);
    }

    /** @return array{code: string, game: string, name: string, thumbnail: ?string, collection: ?string, badges: list<string>, featured: bool, presented: bool} */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
