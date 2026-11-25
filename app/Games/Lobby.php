<?php

namespace App\Games;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The casino's shelves and filters, built from the catalogue for the landing panel and /casino.
 * Labels say what a shelf is: "Featured" is our curated pick, "Most played" comes from the launch
 * log and only appears when there is enough of it (App\Games\MostPlayed).
 */
class Lobby
{
    public function __construct(
        private readonly GameCatalogue $catalogue,
        private readonly MostPlayed $mostPlayed,
        private readonly Favourites $favourites,
    ) {}

    /** Built-in /casino shelves. A collection may not reuse one of these keys (it would be shadowed). */
    public const BUILT_IN_FILTERS = ['all' => 'All games', 'slots' => 'Slots', 'scratch-cards' => 'Scratch cards', 'coin-flip' => 'Coin flip', 'roulette' => 'Roulette', 'jet' => 'Jet', 'jackpot' => 'Jackpot slots', 'favourites' => 'Favourites'];

    /**
     * Shelves the side menu always links to (it never reads the catalogue), so their pages always exist: while the
     * API offers none of the game, the page shows an empty shelf and only the casino tab is hidden.
     */
    public const MENU_FILTERS = ['coin-flip', 'roulette', 'jet'];

    /** @var array<string, Collection<int, GameTitle>> */
    private array $byGame = [];

    /** Every title of one game (`slots`, `scratch`), in lobby order. @return Collection<int, GameTitle> */
    public function titlesOf(string $game): Collection
    {
        return $this->byGame[$game] ??= $this->catalogue->titles()->filter(fn (GameTitle $title) => $title->game === $game)->values();
    }

    /** @return Collection<int, GameTitle> */
    public function slots(): Collection
    {
        return $this->titlesOf('slots');
    }

    /** @return Collection<int, GameTitle> */
    public function scratchCards(): Collection
    {
        return $this->titlesOf('scratch');
    }

    /** Coin Flip's products, one tile each. Empty while KadiApi doesn't offer the game. @return Collection<int, GameTitle> */
    public function coinFlips(): Collection
    {
        return $this->titlesOf('coinflip');
    }

    /** Roulette's products, one tile each. Empty while KadiApi doesn't offer the game. @return Collection<int, GameTitle> */
    public function roulettes(): Collection
    {
        return $this->titlesOf('roulette');
    }

    /** Kadi Jet's products, one tile each. Empty while KadiApi doesn't offer the game. @return Collection<int, GameTitle> */
    public function jets(): Collection
    {
        return $this->titlesOf('jet');
    }

    /** config/games.php collections that may be shelves (none named like a built-in one). @return array<string, array<string, mixed>> */
    public function collectionConfig(): array
    {
        return array_diff_key(config('games.collections', []), self::BUILT_IN_FILTERS + ['featured' => true, 'most-played' => true]);
    }

    /** @return Collection<int, GameTitle> */
    public function jackpotSlots(): Collection
    {
        return $this->slots()->filter(fn (GameTitle $title) => $title->hasBadge('jackpot'))->values();
    }

    /**
     * Slot shelves for the landing's theme chips, in order. Empty shelves are left out.
     *
     * @return list<array{key: string, label: string, titles: Collection<int, GameTitle>}>
     */
    public function themes(): array
    {
        $shelves = [
            ['key' => 'featured', 'label' => 'Featured', 'titles' => $this->catalogue->featured()],
            ['key' => 'most-played', 'label' => 'Most played', 'titles' => $this->mostPlayed->titles()],
        ];

        foreach ($this->collectionConfig() as $key => $collection) {
            $shelves[] = ['key' => $key, 'label' => $collection['label'], 'titles' => $this->catalogue->collection($key)];
        }

        $shelves[] = ['key' => 'jackpot', 'label' => 'Jackpot slots', 'titles' => $this->jackpotSlots()];

        return array_values(array_filter($shelves, fn (array $shelf) => $shelf['titles']->isNotEmpty()));
    }

    /**
     * Collection cards: label, how many slots, three covers.
     *
     * @return list<array{key: string, label: string, count: int, covers: list<string>}>
     */
    public function collections(): array
    {
        $cards = [];

        foreach ($this->collectionConfig() as $key => $collection) {
            $titles = $this->catalogue->collection($key);

            if ($titles->isEmpty()) {
                continue;
            }

            $cards[] = [
                'key' => $key,
                'label' => $collection['label'],
                'count' => $titles->count(),
                'covers' => $titles->pluck('thumbnail')->filter()->take(3)->values()->all(),
            ];
        }

        return $cards;
    }

    /**
     * The /casino filters, in tab order: key => label. The first is the default (no URL segment).
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        $filters = self::BUILT_IN_FILTERS;

        // A game the API doesn't offer gets no empty tab.
        if ($this->coinFlips()->isEmpty()) {
            unset($filters['coin-flip']);
        }

        if ($this->roulettes()->isEmpty()) {
            unset($filters['roulette']);
        }

        if ($this->jets()->isEmpty()) {
            unset($filters['jet']);
        }

        foreach ($this->collectionConfig() as $key => $collection) {
            $filters[$key] = $collection['label'];
        }

        return $filters;
    }

    /**
     * Every shelf that has a page: the tabs (filters()) plus the menu's always-linked shelves.
     *
     * @return array<string, string>
     */
    public function pages(): array
    {
        return $this->filters() + array_intersect_key(self::BUILT_IN_FILTERS, array_flip(self::MENU_FILTERS));
    }

    /** @return Collection<int, GameTitle>|null null for an unknown filter */
    public function filter(string $key, ?User $user): ?Collection
    {
        return match (true) {
            $key === 'all' => $this->catalogue->titles(),
            $key === 'slots' => $this->slots(),
            $key === 'scratch-cards' => $this->scratchCards(),
            $key === 'coin-flip' => $this->coinFlips()->isEmpty() ? null : $this->coinFlips(),
            $key === 'roulette' => $this->roulettes()->isEmpty() ? null : $this->roulettes(),
            $key === 'jet' => $this->jets()->isEmpty() ? null : $this->jets(),
            $key === 'jackpot' => $this->jackpotSlots(),
            $key === 'favourites' => $this->favourites->titles($user),
            array_key_exists($key, $this->collectionConfig()) => $this->catalogue->collection($key),
            default => null,
        };
    }
}
