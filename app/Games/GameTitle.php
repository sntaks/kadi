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

    /** URL slug: the product code without its game prefix, in kebab case (`slots_berry_blast` → `berry-blast`). */
    public function slug(): string
    {
        $prefix = (string) config("games.games.{$this->game}.prefix", '');
        $name = $prefix !== '' && str_starts_with($this->code, $prefix) ? substr($this->code, strlen($prefix)) : $this->code;

        return str_replace('_', '-', $name);
    }

    /** The in-site play page, or null while games do not open inside the site (PLATFORM_TOKEN_LAUNCH off). */
    public function playUrl(): ?string
    {
        return config('platform.token_launch') ? route('play', ['game' => $this->game, 'title' => $this->slug()]) : null;
    }

    /** The crawlable game page (/games/{slug}), or null while those pages are off or this title has none. */
    public function pageUrl(): ?string
    {
        return app(GameCatalogue::class)->pageUrl($this);
    }

    /** "Slot" or "Scratch card". */
    public function typeLabel(): string
    {
        return $this->game === 'scratch' ? 'Scratch card' : 'Slot';
    }

    /** "Pharaoh's vault", "Scratch card"… the line under the tile name. */
    public function kindLabel(): ?string
    {
        if ($this->collection !== null) {
            return config("games.collections.{$this->collection}.label");
        }

        return $this->game === 'scratch' ? 'Scratch card' : null;
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
