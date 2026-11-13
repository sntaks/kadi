<?php

namespace App\Predict;

use App\Events\PredictMarketsChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only caller of GET /predict/updates. One worker (`php artisan predict:feed`) ticks every few seconds for every
 * open page, keeps the live part of each market (`LiveState`) in the cache and broadcasts what players would see
 * change on the public `predict.markets` channel. Pages never call /updates themselves.
 *
 * Cursor: the first answer's `server_time` is the next `since`, sent unchanged (the API looks back 3 s itself).
 * While `has_more` is true the same `since` is asked again with `after_id`; a window too big for one tick is
 * finished on the next. A 422 (`since` too old, or a cursor the API no longer accepts) starts again from the list.
 *
 * Cache: one record per market (`{api, shown, hash, changed_at}`) and an index of ids, so a tick rewrites what moved,
 * never one blob of every market. Only public market data is kept: no player, no amount staked by anyone.
 */
class MarketFeed
{
    public const CURSOR_KEY = 'predict.feed.cursor';

    public const INDEX_KEY = 'predict.feed.index';

    public const REFERENCE_KEY = 'predict.feed.reference';

    public const OK_AT_KEY = 'predict.feed.ok_at';

    public const VERSION_KEY = 'predict.feed.version';

    public const LOCK_KEY = 'predict.feed.lock';

    /** Markets that are over (no more bets, no more price moves) leave the feed after this long. */
    private const FINISHED = ['resolved', 'settled', 'voided'];

    /** @var array<int, array<string, mixed>> what changed this tick, by market id */
    private array $changed = [];

    public function __construct(private PredictApi $api) {}

    public static function recordKey(int $id): string
    {
        return "predict.feed.market.{$id}";
    }

    /**
     * One pass: ask the API for changes (or load the list when there is no cursor), re-derive the changes the API
     * never announces, and broadcast what moved. Only one process ticks at a time.
     *
     * @return array{ran: bool, ok: bool, changed: int}
     */
    public function tick(): array
    {
        $lock = Cache::lock(self::LOCK_KEY, (int) config('predict.feed.lock_seconds', 60));

        if (! $lock->get()) {
            return ['ran' => false, 'ok' => false, 'changed' => 0];
        }

        $this->changed = [];

        try {
            $ok = Cache::get(self::CURSOR_KEY) === null ? $this->bootstrap() : $this->poll();

            if ($ok) {
                Cache::forever(self::OK_AT_KEY, Date::now()->getTimestamp());
            }

            // The cut-off passes and prices stall whether or not the API answered this time.
            $this->rederive();
            $this->broadcast();

            return ['ran' => true, 'ok' => $ok, 'changed' => count($this->changed)];
        } finally {
            $lock->release();
        }
    }

    /**
     * What a page shows for this market now, or null when the feed does not know it.
     *
     * @return array{shown: array<string, mixed>, api: array<string, mixed>}|null
     */
    public function live(int $id): ?array
    {
        return $this->many([$id])[$id] ?? null;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{shown: array<string, mixed>, api: array<string, mixed>}>
     */
    public function many(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $now = Date::now();
        $stalled = $this->referenceStalled();
        $live = [];

        foreach (Cache::many(array_map(self::recordKey(...), $ids)) as $record) {
            if (is_array($record) && is_array($record['api'] ?? null)) {
                $id = (int) $record['api']['id'];
                $live[$id] = ['api' => $record['api'], 'shown' => LiveState::derive($record['api'], $now, $stalled)];
            }
        }

        return $live;
    }

    /**
     * No good answer from the API for a while: pages say prices may be out of date. The quote stays binding.
     */
    public function isStale(): bool
    {
        $okAt = Cache::get(self::OK_AT_KEY);

        return ! is_numeric($okAt)
            || Date::now()->getTimestamp() - (int) $okAt > (int) config('predict.feed.stale_after_seconds', 30);
    }

    /** Seconds since the API last answered the feed, or null when it never has. */
    public function secondsSinceOk(): ?int
    {
        $okAt = Cache::get(self::OK_AT_KEY);

        return is_numeric($okAt) ? max(0, Date::now()->getTimestamp() - (int) $okAt) : null;
    }

    /**
     * Grows every time any market changes: a page that saw the same version has nothing to re-render.
     */
    public function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    /**
     * Polymarket's prices are older than the API's own guard, so no fixed-odds multiplier is on offer. Measured on
     * the API's clock (its `server_time` against its `reference_prices_at`) plus the time since we asked, so a skew
     * between the two servers never counts.
     */
    public function referenceStalled(): bool
    {
        $reference = Cache::get(self::REFERENCE_KEY);

        if (! is_array($reference) || ! isset($reference['lag'], $reference['fetched_at'])) {
            return false;
        }

        $age = (int) $reference['lag'] + (Date::now()->getTimestamp() - (int) $reference['fetched_at']);

        return $age > (int) config('predict.feed.reference_price_max_age_seconds', 45);
    }

    /** Forget the cursor: the next tick loads every market from the list again. */
    public function reset(): void
    {
        Cache::forget(self::CURSOR_KEY);
    }

    /**
     * No cursor yet (first run, cache cleared, or the API refused the old one): read the whole list, then follow
     * /updates from just before we started. The 60 s margin covers a clock difference between the two servers;
     * a market seen twice is harmless (only a changed hash is broadcast).
     */
    private function bootstrap(): bool
    {
        $since = Date::now()->subSeconds(60)->utc()->format('Y-m-d\TH:i:s\Z');
        $markets = [];
        $page = 1;
        $maxPages = (int) config('predict.feed.bootstrap_max_pages', 20);

        do {
            $response = $this->api->markets(['page' => $page, 'per_page' => 100]);

            if (! $response->ok() || ! is_array($response->data['items'] ?? null)) {
                return false;
            }

            array_push($markets, ...array_values(array_filter($response->data['items'], is_array(...))));
            $lastPage = (int) ($response->data['pagination']['last_page'] ?? $page);
            $page++;
        } while ($page <= $lastPage && $page <= $maxPages);

        if ($lastPage > $maxPages) {
            Log::warning('Predictions: the market list is longer than the feed reads at start', ['pages' => $lastPage]);
        }

        $ids = $this->ingest($markets);

        // Records of markets the list no longer shows are dropped with the old index.
        $gone = array_diff($this->index(), $ids);
        Cache::deleteMultiple(array_map(self::recordKey(...), $gone));
        Cache::forever(self::INDEX_KEY, $ids);
        Cache::forever(self::CURSOR_KEY, ['since' => $since, 'after_id' => null, 'next_since' => null]);

        return true;
    }

    /**
     * Follow /updates from the cursor, at most `max_pages` pages this tick.
     */
    private function poll(): bool
    {
        $cursor = (array) Cache::get(self::CURSOR_KEY);
        $since = (string) ($cursor['since'] ?? '');
        $afterId = is_numeric($cursor['after_id'] ?? null) ? (int) $cursor['after_id'] : null;
        $nextSince = $cursor['next_since'] ?? null;

        for ($page = 0; $page < (int) config('predict.feed.max_pages', 10); $page++) {
            $response = $this->api->updates($since, $afterId);

            if ($response->status === 422) {
                // `since` is older than the API keeps: start again from the list.
                Log::info('Predictions: the feed cursor was refused, reloading the list');
                $this->reset();

                return $this->bootstrap();
            }

            if (! $response->ok() || ! is_array($response->data['markets'] ?? null)) {
                Cache::forever(self::CURSOR_KEY, ['since' => $since, 'after_id' => $afterId, 'next_since' => $nextSince]);

                return false;
            }

            $serverTime = Format::string($response->data['server_time'] ?? null);

            // The window's first answer sets the next `since`; later pages of the same window do not.
            if ($afterId === null && $nextSince === null) {
                $nextSince = $serverTime;
            }

            $this->rememberReference($response->data, $serverTime);
            $markets = array_values(array_filter($response->data['markets'], is_array(...)));
            $ids = $this->ingest($markets);
            $this->addToIndex($ids);

            if (($response->data['has_more'] ?? false) !== true || $ids === []) {
                Cache::forever(self::CURSOR_KEY, ['since' => $nextSince ?? $since, 'after_id' => null, 'next_since' => null]);

                return true;
            }

            $afterId = max($ids);
        }

        // The window is not finished: carry on from here next tick.
        Cache::forever(self::CURSOR_KEY, ['since' => $since, 'after_id' => $afterId, 'next_since' => $nextSince]);

        return true;
    }

    /**
     * Store what the API said about these markets and note the ones whose shown state moved.
     *
     * @param  list<array<string, mixed>>  $markets
     * @return list<int> the ids stored
     */
    private function ingest(array $markets): array
    {
        $now = Date::now();
        $stalled = $this->referenceStalled();
        $records = [];

        foreach ($markets as $market) {
            $api = LiveState::fromApi($market, $now);

            if ($api !== null) {
                $records[$api['id']] = $api;
            }
        }

        if ($records === []) {
            return [];
        }

        $existing = Cache::many(array_map(self::recordKey(...), array_keys($records)));
        $writes = [];

        foreach ($records as $id => $api) {
            $writes[self::recordKey($id)] = $this->record($api, $existing[self::recordKey($id)] ?? null, $now, $stalled);
        }

        Cache::putMany($writes, (int) config('predict.feed.record_ttl_seconds', 172800));

        return array_keys($records);
    }

    /**
     * Re-derive every known market at our clock: the cut-off passing and a price stall change what players see
     * without the API saying so. Finished markets that have not moved for a day leave the feed.
     */
    private function rederive(): void
    {
        $ids = $this->index();

        if ($ids === []) {
            return;
        }

        $now = Date::now();
        $stalled = $this->referenceStalled();
        $keep = [];
        $writes = [];
        $finishedBefore = $now->getTimestamp() - 86400;

        foreach (Cache::many(array_map(self::recordKey(...), $ids)) as $key => $record) {
            if (! is_array($record) || ! is_array($record['api'] ?? null)) {
                continue;
            }

            $id = (int) $record['api']['id'];

            if (in_array($record['api']['status'] ?? null, self::FINISHED, true) && (int) ($record['changed_at'] ?? 0) < $finishedBefore) {
                Cache::forget($key);

                continue;
            }

            $keep[] = $id;
            $updated = $this->record($record['api'], $record, $now, $stalled);

            if ($updated['hash'] !== ($record['hash'] ?? null)) {
                $writes[$key] = $updated;
            }
        }

        if ($writes !== []) {
            Cache::putMany($writes, (int) config('predict.feed.record_ttl_seconds', 172800));
        }

        if (count($keep) !== count($ids)) {
            Cache::forever(self::INDEX_KEY, $keep);
        }
    }

    /**
     * @param  array<string, mixed>  $api
     * @return array{api: array<string, mixed>, shown: array<string, mixed>, hash: string, changed_at: int}
     */
    private function record(array $api, mixed $existing, CarbonInterface $now, bool $stalled): array
    {
        $shown = LiveState::derive($api, $now, $stalled);
        $hash = LiveState::hash($shown);
        $moved = ! is_array($existing) || ($existing['hash'] ?? null) !== $hash;

        if ($moved) {
            $this->changed[$api['id']] = $shown + ['changed_at' => $now->getTimestamp()];
        }

        return [
            'api' => $api,
            'shown' => $shown,
            'hash' => $hash,
            'changed_at' => $moved ? $now->getTimestamp() : (int) ($existing['changed_at'] ?? $now->getTimestamp()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rememberReference(array $data, ?string $serverTime): void
    {
        $pricesAt = Format::time($data['reference_prices_at'] ?? null);
        $apiNow = Format::time($serverTime);

        // No open fixed-odds market (null): nothing can stall.
        if ($pricesAt === null || $apiNow === null) {
            Cache::forget(self::REFERENCE_KEY);

            return;
        }

        Cache::forever(self::REFERENCE_KEY, [
            'lag' => max(0, $apiNow->getTimestamp() - $pricesAt->getTimestamp()),
            'fetched_at' => Date::now()->getTimestamp(),
        ]);
    }

    /** @return list<int> */
    private function index(): array
    {
        $index = Cache::get(self::INDEX_KEY);

        return is_array($index) ? array_values(array_map(intval(...), $index)) : [];
    }

    /** @param  list<int>  $ids */
    private function addToIndex(array $ids): void
    {
        $index = $this->index();
        $new = array_diff($ids, $index);

        if ($new !== []) {
            Cache::forever(self::INDEX_KEY, [...$index, ...$new]);
        }
    }

    /**
     * Tell open pages what moved, in batches. A Reverb outage must never stop the feed: pages still poll.
     */
    private function broadcast(): void
    {
        if ($this->changed === []) {
            return;
        }

        // Only the lock holder writes it, so a plain read-and-write is safe (and works on every cache store).
        Cache::forever(self::VERSION_KEY, $this->version() + 1);

        foreach (array_chunk(array_values($this->changed), (int) config('predict.feed.broadcast_batch', 100)) as $batch) {
            try {
                broadcast(new PredictMarketsChanged($batch));
            } catch (Throwable $e) {
                Log::warning('Predictions: could not broadcast market changes', ['error' => $e::class]);

                return;
            }
        }
    }
}
