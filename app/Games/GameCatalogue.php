<?php

namespace App\Games;

use App\Services\KadiApiService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * The lobby: active games and products from the Kadi API (`GET /games`), joined
 * with presentation from config/games.php.
 *
 * The API is the authority on what is playable. Its answer is kept fresh for a
 * few minutes; when the API is down the last good answer is served for up to a
 * day, and with nothing cached the lobby is empty rather than broken. Only one
 * process refreshes at a time; the others serve the stale copy meanwhile.
 *
 * Bound per request (scoped), so a layout and a component share one pass.
 */
class GameCatalogue
{
    public const FRESH_KEY = 'games.catalogue.fresh';

    public const STALE_KEY = 'games.catalogue.stale';

    public const LOCK_KEY = 'games.catalogue.refresh';

    public const OUTAGE_BACKOFF_SECONDS = 60;

    /** @var Collection<string, Game>|null */
    private ?Collection $games = null;

    /** @var array<string, string>|null product code => collection key */
    private ?array $collections = null;

    /** @var array<string, list<string>>|null product code => badges */
    private ?array $badges = null;

    /** @var array<string, string>|null product code => game, for products config/games.php presents */
    private ?array $configured = null;

    public function __construct(private readonly KadiApiService $api) {}

    /** @return Collection<string, Game> keyed by game code, in lobby order */
    public function games(): Collection
    {
        return $this->games ??= collect($this->rawGames())
            ->map(fn (array $game) => $this->present($game))
            ->filter()
            ->sortBy('sortOrder')
            ->keyBy('code');
    }

    public function game(string $code): ?Game
    {
        return $this->games()->get($code);
    }

    /** Every lobby tile across games, in lobby order. @return Collection<int, GameTitle> */
    public function titles(): Collection
    {
        return $this->games()->flatMap(fn (Game $game) => $game->titles)->values();
    }

    public function title(string $code): ?GameTitle
    {
        return $this->titles()->first(fn (GameTitle $title) => $title->code === $code);
    }

    /** @return Collection<int, GameTitle> */
    public function collection(string $key): Collection
    {
        return $this->titles()->filter(fn (GameTitle $title) => $title->collection === $key)->values();
    }

    /** @return Collection<int, GameTitle> in the order config/games.php lists them */
    public function featured(): Collection
    {
        $titles = $this->titles()->keyBy('code');

        return collect(config('games.featured', []))
            ->map(fn (string $code) => $titles->get($code))
            ->filter()
            ->values();
    }

    /** Forget the fresh copy so the next read asks the API (the stale copy stays as a fallback). */
    public function refresh(): void
    {
        Cache::forget(self::FRESH_KEY);
        $this->games = null;
    }

    /** @return list<array<string, mixed>> */
    private function rawGames(): array
    {
        $fresh = Cache::get(self::FRESH_KEY);

        if (is_array($fresh)) {
            return $fresh;
        }

        $stale = Cache::get(self::STALE_KEY);
        $lock = Cache::lock(self::LOCK_KEY, (int) config('games.catalogue.timeout_seconds', 5) + 5);

        if (! $lock->get()) {
            // Someone else is refreshing: serve the stale copy now, or wait for theirs if there is none.
            if (is_array($stale)) {
                return $stale;
            }

            try {
                $lock->block((int) config('games.catalogue.timeout_seconds', 5) + 1);
            } catch (LockTimeoutException) {
                return [];
            }

            $fresh = Cache::get(self::FRESH_KEY);

            if (is_array($fresh)) {
                $lock->release();

                return $fresh;
            }
        }

        try {
            return $this->fetchAndStore($stale);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  mixed  $stale  the last good copy, if any
     * @return list<array<string, mixed>>
     */
    private function fetchAndStore(mixed $stale): array
    {
        try {
            $games = $this->api->games((int) config('games.catalogue.timeout_seconds', 5));
        } catch (ConnectionException|RequestException|UnexpectedValueException $e) {
            Log::warning('Game catalogue: Kadi API unavailable, serving the last good copy', [
                'error' => class_basename($e),
                'status' => $e instanceof RequestException ? $e->response->status() : null,
            ]);

            $stale = is_array($stale) ? $stale : [];

            // Back off so an outage costs one slow request a minute, not one per page view.
            Cache::put(self::FRESH_KEY, $stale, self::OUTAGE_BACKOFF_SECONDS);

            return $stale;
        }

        Cache::put(self::FRESH_KEY, $games, (int) config('games.catalogue.fresh_seconds', 300));
        Cache::put(self::STALE_KEY, $games, (int) config('games.catalogue.stale_seconds', 86400));

        return $games;
    }

    /** @param  array<string, mixed>  $raw */
    private function present(array $raw): ?Game
    {
        $code = $raw['code'] ?? null;
        $launchUrl = $raw['launch_url'] ?? null;

        if (! is_string($code) || ! is_string($launchUrl) || ($raw['status'] ?? 'active') !== 'active') {
            return null;
        }

        if (! self::isAllowedLaunchUrl($launchUrl)) {
            Log::warning('Game catalogue: game left out, its launch URL is not on an allowed host', ['game' => $code]);

            return null;
        }

        $config = config("games.games.{$code}", []);

        $products = collect(is_array($raw['products'] ?? null) ? $raw['products'] : [])
            ->filter(fn ($product) => is_array($product)
                && is_string($product['code'] ?? null)
                && ($product['status'] ?? 'active') === 'active')
            ->values();

        $titles = ($config['titles'] ?? false)
            ? $products->map(fn (array $product) => $this->presentTitle($code, $product))->all()
            : [];

        return new Game(
            code: $code,
            name: is_string($raw['name'] ?? null) ? $raw['name'] : $code,
            launchUrl: $launchUrl,
            sortOrder: (int) ($raw['sort_order'] ?? 0),
            tagline: $config['tagline'] ?? null,
            products: $products->pluck('code')->all(),
            titles: $titles,
        );
    }

    /** https only, on a host from `games.catalogue.launch_hosts` (`*.` matches subdomains). */
    public static function isAllowedLaunchUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach (config('games.catalogue.launch_hosts', []) as $allowed) {
            $allowed = strtolower($allowed);

            if ($host === $allowed || (str_starts_with($allowed, '*.') && Str::endsWith($host, substr($allowed, 1)))) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $product */
    private function presentTitle(string $game, array $product): GameTitle
    {
        $code = $product['code'];

        return new GameTitle(
            code: $code,
            game: $game,
            name: is_string($product['name'] ?? null) ? $product['name'] : $code,
            thumbnail: GameArtwork::thumbnailUrl($code),
            collection: $this->collectionMap()[$code] ?? null,
            badges: $this->badgeMap()[$code] ?? [],
            featured: in_array($code, config('games.featured', []), true),
            presented: isset(($this->configured ??= GameArtwork::configuredProducts())[$code]),
        );
    }

    /** @return array<string, string> */
    private function collectionMap(): array
    {
        if ($this->collections === null) {
            $this->collections = [];

            foreach (config('games.collections', []) as $key => $collection) {
                foreach ($collection['products'] ?? [] as $code) {
                    $this->collections[$code] ??= $key;
                }
            }
        }

        return $this->collections;
    }

    /** @return array<string, list<string>> */
    private function badgeMap(): array
    {
        if ($this->badges === null) {
            $this->badges = [];

            foreach (config('games.badges', []) as $badge => $codes) {
                foreach ($codes as $code) {
                    $this->badges[$code][] = $badge;
                }
            }
        }

        return $this->badges;
    }
}
