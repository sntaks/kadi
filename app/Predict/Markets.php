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

    /**
     * One page of the list. `available` is false when the API could not be read and no earlier copy exists.
     *
     * @param  array{source?: ?string, category?: ?string, sort?: ?string, page?: ?int}  $filters
     * @return array{markets: list<Market>, page: int, last_page: int, total: int, available: bool}
     */
    public function list(array $filters = []): array
    {
        $filters = $this->filters($filters);
        $data = $this->remember('predict.list.'.md5((string) json_encode($filters)), (int) config('predict.cache.list_seconds', 10), function () use ($filters) {
            $response = $this->api->markets($filters);

            return $response->ok() && is_array($response->data['items'] ?? null) ? $response->data : null;
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
