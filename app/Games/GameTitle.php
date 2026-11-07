<?php

namespace App\Games;

/**
 * One lobby tile inside a game: a slot or a scratch card. Name and status come
 * from the Kadi API; artwork, collection and badges from config/games.php.
 *
 * `code` is the API's product code (`slots_berry_blast`), sent to the game server and
 * matched against wagers. `key` is that code without its game prefix (`berry_blast`):
 * the stable name config/games.php, thumbnails, URL slugs, favourites and launch history
 * use, so a prefix the API adds or drops never renames them.
 */
final readonly class GameTitle
{
    public string $key;

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
    ) {
        $this->key = self::keyFor($game, $code);
    }

    /** The product code without its `{game}_` prefix (`slots_berry_blast` → `berry_blast`, `scratch_safari` → `safari`). */
    public static function keyFor(string $game, string $code): string
    {
        return str_starts_with($code, $game.'_') && strlen($code) > strlen($game) + 1 ? substr($code, strlen($game) + 1) : $code;
    }

    /** True for this title's API code or its key. */
    public function is(string $codeOrKey): bool
    {
        return $codeOrKey === $this->code || $codeOrKey === $this->key;
    }

    /** False for a product the API lists but config/games.php does not know (a plain fallback tile). */
    public function isPresented(): bool
    {
        return $this->presented;
    }

    /** URL slug: the key in kebab case (`slots_berry_blast` → `berry-blast`). */
    public function slug(): string
    {
        return str_replace('_', '-', $this->key);
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

    /** @return array{code: string, key: string, game: string, name: string, thumbnail: ?string, collection: ?string, badges: list<string>, featured: bool, presented: bool} */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
