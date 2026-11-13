<?php

namespace App\Predict;

use App\Predict\Data\Market;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * What the pages read: the market list, one market and its price history, cached briefly. A failed read serves the
 * last good copy (up to `predict.cache.stale_seconds`) and backs off, like the game catalogue, so an API outage costs
 * one slow request per back-off, not one per page view.
 *
 * The live part of every market (status, betting, odds) comes from whichever is newer: this cache or the feed
 * (`MarketFeed`), then derived at this moment (`LiveState`), so a page never shows a price the feed has replaced
 * or a bet button past the cut-off.
 */
class Markets
{
    public function __construct(private PredictApi $api, private MarketFeed $feed) {}

    /** How a category reads in menus and chips. */
    public static function categoryLabel(string $category): string
    {
        return ['sport' => 'Sport', 'politics' => 'Politics', 'entertainment' => 'Entertainment'][$category] ?? ucfirst($category);
    }

    /**
     * Short names for the markets of one event: what differs between their questions, with the words they all share
     * at the start and the end removed ("Will Arsenal win the 2026-27 EPL?" and "Will Chelsea win the 2026-27 EPL?"
     * read "Arsenal" and "Chelsea"). A question that would lose everything keeps its full text.
     *
     * @param  list<Market>  $markets
     * @return array<int, string> by market id
     */
    public static function outcomeLabels(array $markets): array
    {
        $words = array_map(fn (Market $m) => preg_split('/\s+/', trim(rtrim($m->question, '?'))) ?: [], $markets);
        $labels = [];

        if (count($words) < 2) {
            foreach ($markets as $market) {
                $labels[$market->id] = $market->question;
            }

            return $labels;
        }

        $shortest = min(array_map('count', $words));
        $same = fn (int $i) => count(array_unique(array_map(fn (array $w) => mb_strtolower($w[$i]), $words))) === 1;
        $sameFromEnd = fn (int $i) => count(array_unique(array_map(fn (array $w) => mb_strtolower($w[count($w) - 1 - $i]), $words))) === 1;

        for ($head = 0; $head < $shortest && $same($head); $head++);
        for ($tail = 0; $tail < $shortest - $head && $sameFromEnd($tail); $tail++);

        // A difference with no letters ("15," against "30,") needs its neighbours to read: "November 15, 2026".
        $bare = fn (int $head, int $tail) => collect($words)->every(fn (array $w) => ! preg_match('/[\pL%$]/u', implode(' ', array_slice($w, $head, count($w) - $head - $tail))));

        while ($bare($head, $tail) && ($head > 0 || $tail > 0)) {
            $head = max(0, $head - 1);
            $tail = max(0, $tail - 1);
        }

        foreach ($markets as $i => $market) {
            $label = rtrim(implode(' ', array_slice($words[$i], $head, count($words[$i]) - $head - $tail)), ' ,;:');
            $labels[$market->id] = $label !== '' ? mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1) : $market->question;
        }

        return $labels;
    }

    /**
     * The Yes/No labels of markets this site has already read, from the cache only (My bets must not call the API
     * once per bet). Markets not in the cache are left out; their bets read "Yes"/"No".
     *
     * @param  list<int>  $ids
     * @return array<int, array{yes: string, no: string}>
     */
    public static function cachedLabels(array $ids): array
    {
        $labels = [];

        foreach (array_unique($ids) as $id) {
            $data = Cache::get("predict.market.{$id}.fresh") ?? Cache::get("predict.market.{$id}.stale");
            $odds = is_array($data['market']['odds'] ?? null) ? $data['market']['odds'] : null;

            if ($odds !== null) {
                $labels[$id] = ['yes' => Format::string($odds['yes_label'] ?? null) ?? 'Yes', 'no' => Format::string($odds['no_label'] ?? null) ?? 'No'];
            }
        }

        return $labels;
    }

    /** How a sort reads in the lobby. */
    public static function sortLabel(string $sort): string
    {
        return ['closing_soon' => 'Closing soon', 'trending' => 'Trending', 'new' => 'New'][$sort] ?? ucfirst(str_replace('_', ' ', $sort));
    }

    /**
     * One page of the list. `available` is false when the API could not be read and no earlier copy exists.
     *
     * A list answer carries every market in full, so each one also primes that market's own copy: opening it from
     * the list (the market sheet) then needs no call of its own.
     *
     * @param  array{source?: ?string, category?: ?string, sort?: ?string, page?: ?int}  $filters
     * @return array{markets: list<Market>, page: int, last_page: int, total: int, available: bool}
     */
    public function list(array $filters = []): array
    {
        $filters = $this->filters($filters);
        $key = 'predict.list.'.md5((string) json_encode($filters));
        $data = $this->remember($key, (int) config('predict.cache.list_seconds', 10), function () use ($filters) {
            $response = $this->api->markets($filters);

            if (! $response->ok() || ! is_array($response->data['items'] ?? null)) {
                return null;
            }

            $this->prime(array_values(array_filter($response->data['items'], is_array(...))));

            return $response->data;
        });

        if ($data === null) {
            return ['markets' => [], 'page' => $filters['page'], 'last_page' => 1, 'total' => 0, 'available' => false];
        }

        $items = array_values(array_filter($data['items'], is_array(...)));
        $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];

        return [
            'markets' => $this->withLive($items, (int) $data['fetched_at']),
            'page' => (int) ($pagination['page'] ?? $filters['page']),
            'last_page' => max(1, (int) ($pagination['last_page'] ?? 1)),
            'total' => (int) ($pagination['total'] ?? count($items)),
            'available' => true,
        ];
    }

    /**
     * Read a list page into the cache unless a fresh copy is already there (prefetching: the next "Load more" page
     * after a response, the lobby's first pages from the feed worker). Never throws.
     *
     * @param  array{source?: ?string, category?: ?string, sort?: ?string, page?: ?int}  $filters
     */
    public function warm(array $filters): void
    {
        $key = 'predict.list.'.md5((string) json_encode($this->filters($filters)));

        if (! Cache::has("{$key}.fresh") && ! Cache::has("{$key}.backoff")) {
            $this->list($filters);
        }
    }

    /**
     * The lists a visit starts with, kept warm by the feed worker so a first visit never waits on the API: the
     * lobby's (every category, both sections, the default sort) and the landing's trending shelf.
     */
    public function warmLobby(): void
    {
        foreach (['', ...(array) config('predict.categories')] as $category) {
            foreach (['polymarket', 'custom'] as $source) {
                $this->warm(['category' => $category, 'source' => $source, 'page' => 1]);
            }
        }

        $this->warm(['sort' => 'trending']);
    }

    /**
     * One market, or null when the API does not show it (unknown id, a draft) or cannot be read.
     */
    public function find(int $id): ?Market
    {
        $data = $this->remember("predict.market.{$id}", (int) config('predict.cache.market_seconds', 5), function () use ($id) {
            $response = $this->api->market($id);

            if ($response->status === 404) {
                return ['missing' => true];
            }

            return $response->ok() && is_numeric($response->data['id'] ?? null) ? ['market' => $response->data] : null;
        });

        if (! is_array($data['market'] ?? null)) {
            return null;
        }

        return $this->withLive([$data['market']], (int) $data['fetched_at'])[0] ?? null;
    }

    /**
     * The YES probability over time, oldest first, for the chart. Empty when there is none or the API is down.
     *
     * @return list<array{at: string, yes: float}>
     */
    public function history(int $id): array
    {
        $data = $this->remember("predict.history.{$id}", (int) config('predict.cache.history_seconds', 30), function () use ($id) {
            $response = $this->api->history($id);

            return $response->ok() && is_array($response->data['points'] ?? null) ? ['points' => $response->data['points']] : null;
        });

        $points = [];

        foreach ((array) ($data['points'] ?? []) as $point) {
            $at = is_array($point) ? Format::time($point['at'] ?? null) : null;
            $yes = is_array($point) ? Format::float($point['yes_probability'] ?? null) : null;

            if ($at !== null && $yes !== null && $yes >= 0 && $yes <= 1) {
                $points[] = ['at' => $at->utc()->toIso8601String(), 'yes' => $yes];
            }
        }

        usort($points, fn (array $a, array $b) => strcmp($a['at'], $b['at']));

        return $points;
    }

    /**
     * Only the filters the API accepts, so a made-up query string can neither fail the call nor fill the cache
     * with keys.
     *
     * @param  array<string, mixed>  $filters
     * @return array{source: ?string, category: ?string, sort: string, page: int, per_page: int}
     */
    private function filters(array $filters): array
    {
        $pick = fn (string $key, string $list) => in_array($filters[$key] ?? null, (array) config("predict.{$list}"), true) ? $filters[$key] : null;

        return [
            'source' => $pick('source', 'sources'),
            'category' => $pick('category', 'categories'),
            'sort' => $pick('sort', 'sorts') ?? (string) config('predict.default_sort'),
            'page' => min(max(1, (int) ($filters['page'] ?? 1)), 1000),
            'per_page' => (int) config('predict.per_page', 24),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<Market>
     */
    private function withLive(array $items, int $fetchedAt): array
    {
        $ids = array_values(array_filter(array_map(fn (array $m) => is_numeric($m['id'] ?? null) ? (int) $m['id'] : null, $items)));
        $live = $this->feed->many($ids);
        $now = Date::now();
        $stalled = $this->feed->referenceStalled();
        $markets = [];

        foreach ($items as $item) {
            $id = is_numeric($item['id'] ?? null) ? (int) $item['id'] : null;
            $own = LiveState::fromApi($item, Date::createFromTimestamp($fetchedAt));
            $feed = $id !== null ? ($live[$id]['api'] ?? null) : null;
            // The newer of the two copies wins; ours on a tie (it was read for this market alone).
            $api = $feed !== null && (int) $feed['fetched_at'] > $fetchedAt ? $feed : $own;

            $market = Market::fromArray($api !== null ? LiveState::apply($item, LiveState::derive($api, $now, $stalled)) : $item);

            if ($market !== null) {
                $markets[] = $market;
            }
        }

        return $markets;
    }

    /**
     * Keep each listed market as that market's own fresh copy, unless a newer one is already there.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function prime(array $items): void
    {
        $now = Date::now()->getTimestamp();
        $fresh = [];
        $stale = [];

        foreach ($items as $item) {
            // Only full market objects: a page must never render from a trimmed one.
            if (is_numeric($item['id'] ?? null) && array_key_exists('description', $item) && array_key_exists('odds', $item)) {
                $data = ['market' => $item, 'fetched_at' => $now];
                $fresh["predict.market.{$item['id']}.fresh"] = $data;
                $stale["predict.market.{$item['id']}.stale"] = $data;
            }
        }

        if ($fresh !== []) {
            Cache::putMany($fresh, (int) config('predict.cache.market_seconds', 5));
            Cache::putMany($stale, (int) config('predict.cache.stale_seconds', 3600));
        }
    }

    /**
     * Fresh copy, else ask the API (unless backing off), else the last good copy. `$fetch` answers the data to keep,
     * or null on failure.
     *
     * @param  callable(): ?array<string, mixed>  $fetch
     * @return array<string, mixed>|null
     */
    private function remember(string $key, int $seconds, callable $fetch): ?array
    {
        $fresh = Cache::get("{$key}.fresh");

        if (is_array($fresh)) {
            return $fresh;
        }

        $stale = Cache::get("{$key}.stale");
        $stale = is_array($stale) ? $stale : null;

        if (Cache::has("{$key}.backoff")) {
            return $stale;
        }

        $data = $fetch();

        if ($data === null) {
            Cache::put("{$key}.backoff", true, (int) config('predict.cache.backoff_seconds', 10));

            return $stale;
        }

        $data['fetched_at'] = Date::now()->getTimestamp();
        Cache::put("{$key}.fresh", $data, $seconds);
        Cache::put("{$key}.stale", $data, (int) config('predict.cache.stale_seconds', 3600));

        return $data;
    }
}
