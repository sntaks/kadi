<?php

namespace App\Predict;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only class that calls the Kadi API's prediction market routes (docs/predictions.md), with the site key. It
 * has no method for the API's staff routes: those belong to GMS and this site never calls them.
 *
 * No call throws on an HTTP failure: each returns a PredictResponse, and a call that got no answer says so.
 * Nothing is retried here; retrying is the caller's choice. A bet is idempotent by its `reference` (the API does
 * not run its Idempotency-Key middleware on these routes, so no such header is sent).
 *
 * A player's `{encryptedIdentifier}` always comes from the signed-in user's `linked_id`, never from input. The
 * site key, that identifier, references, quote ids and stakes are never logged.
 */
class PredictApi
{
    public function configured(): bool
    {
        return filled(config('services.kadi_api.url')) && filled(config('services.kadi_api.key'));
    }

    /**
     * GET /predict/markets: `data.items` and `data.pagination`.
     *
     * @param  array{source?: string, category?: string, sort?: string, status?: string, page?: int, per_page?: int}  $filters
     * @param  string  $timeout  `read` for pages (a player is waiting), `feed` for the background feed worker
     */
    public function markets(array $filters = [], string $timeout = 'read'): PredictResponse
    {
        return $this->send(fn () => $this->http($timeout)->get('predict/markets', array_filter($filters, filled(...))), 'markets');
    }

    public function market(int $id): PredictResponse
    {
        return $this->send(fn () => $this->http('read')->get("predict/markets/{$id}"), 'market');
    }

    /**
     * GET /predict/markets/{id}/history: `data.points[]` of `{at, yes_probability, no_probability}`.
     */
    public function history(int $id): PredictResponse
    {
        return $this->send(fn () => $this->http('read')->get("predict/markets/{$id}/history"), 'history');
    }

    /**
     * GET /predict/markets/{id}/estimate: an indicative multiplier and payout for a stake. Never binding.
     */
    public function estimate(int $id, string $side, float $stake): PredictResponse
    {
        return $this->send(
            fn () => $this->http('read')->get("predict/markets/{$id}/estimate", ['side' => $side, 'stake' => self::amount($stake)]),
            'estimate',
        );
    }

    /**
     * GET /predict/updates: markets whose state or figures changed after `since` (the previous answer's
     * `server_time`, never our own clock once the feed is running). While `has_more` is true, ask again with the
     * same `since` and `after_id` = the last market's id.
     */
    public function updates(string $since, ?int $afterId = null): PredictResponse
    {
        return $this->send(
            // Only the feed worker calls this, in the background: it can wait longer than a page could.
            fn () => $this->http('feed')->get('predict/updates', array_filter(['since' => $since, 'after_id' => $afterId], filled(...))),
            'updates',
        );
    }

    /**
     * POST customers/{id}/predict/quotes: fixed figures for this player, side and stake, good for about 10 seconds.
     */
    public function quote(User $user, int $marketId, string $side, float $stake): PredictResponse
    {
        return $this->forPlayer($user, 'quote', fn (string $path) => $this->http('quote')->post("{$path}/quotes", [
            'market_id' => $marketId,
            'side' => $side,
            'stake' => self::amount($stake),
        ]));
    }

    /**
     * POST customers/{id}/predict/bets. The same `reference` with the same market, side and stake never takes the
     * money twice (`replayed: true`). Pool markets send no `quote_id`.
     */
    public function placeBet(User $user, int $marketId, string $side, float $stake, string $reference, ?string $quoteId = null): PredictResponse
    {
        return $this->forPlayer($user, 'bet', fn (string $path) => $this->http('bet')->post("{$path}/bets", array_filter([
            'market_id' => $marketId,
            'side' => $side,
            'stake' => self::amount($stake),
            'reference' => $reference,
            'quote_id' => $quoteId,
        ], filled(...))));
    }

    /**
     * GET customers/{id}/predict/bets: `data.items` and `data.pagination`. `status` is open, won, lost, void or
     * settled (won + lost + void); anything else is a 422.
     */
    public function bets(User $user, ?string $status = null, int $page = 1, int $perPage = 20): PredictResponse
    {
        return $this->forPlayer($user, 'bets', fn (string $path) => $this->http('read')->get("{$path}/bets", array_filter([
            'status' => $status,
            'page' => $page,
            'per_page' => $perPage,
        ], filled(...))));
    }

    /**
     * An amount as the API reads it: shillings with two decimals.
     */
    public static function amount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * @param  callable(string): Response  $call  Receives `customers/{encryptedIdentifier}/predict`.
     */
    private function forPlayer(User $user, string $action, callable $call): PredictResponse
    {
        if (blank($user->linked_id)) {
            return new PredictResponse(404, 'customer_not_found');
        }

        return $this->send(fn () => $call('customers/'.encryptOpenSSL((string) $user->linked_id).'/predict'), $action);
    }

    private function http(string $kind): PendingRequest
    {
        return Http::baseUrl((string) config('services.kadi_api.url'))
            ->withHeaders(['x-api-key' => (string) config('services.kadi_api.key')])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config("predict.timeouts.{$kind}", 5))
            ->connectTimeout((int) config('predict.timeouts.connect', 3));
    }

    /**
     * @param  callable(): Response  $call
     */
    private function send(callable $call, string $action): PredictResponse
    {
        if (! $this->configured()) {
            Log::error('Predictions: the Kadi API url or site key is not set', ['action' => $action]);

            return PredictResponse::notConfigured();
        }

        try {
            $response = $call();
        } catch (ConnectionException) {
            Log::warning('Predictions: no answer from the Kadi API', ['action' => $action]);

            return PredictResponse::noAnswer();
        }

        $status = $response->status();
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->successful()) {
            $data = $body['data'] ?? [];

            return new PredictResponse($status, null, null, is_array($data) ? $data : []);
        }

        $code = is_string($body['code'] ?? null) ? $body['code'] : null;
        $message = is_string($body['message'] ?? null) ? $body['message'] : null;

        if ($status >= 500 || $status === 401) {
            Log::warning('Predictions: the Kadi API refused a call', ['action' => $action, 'status' => $status, 'code' => $code]);
        }

        unset($body['success'], $body['message'], $body['code'], $body['retryable']);

        $retryAfter = $response->header('Retry-After');

        return new PredictResponse(
            $status,
            $code ?? 'http_'.$status,
            $message,
            $body,
            is_numeric($retryAfter) ? max(0, (int) $retryAfter) : null,
        );
    }
}
