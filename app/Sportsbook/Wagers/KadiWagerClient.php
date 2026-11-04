<?php

namespace App\Sportsbook\Wagers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The sportsbook's calls to the Kadi API wager endpoints (plan §5.3), with the sportsbook's game key; placing a bet
 * also carries the player's token. None of them throws on an HTTP failure: each returns a WagerResponse, and a call
 * that got no answer says so. Retrying is the caller's choice: every wager call is idempotent by its natural key
 * (product and reference; settle and reverse also by version), except redeeming a launch token, which never is.
 *
 * The game key and player tokens are never logged, and neither is any amount or player.
 */
class KadiWagerClient
{
    public function configured(): bool
    {
        return filled(config('services.kadi_api.sportsbook_game_key')) && filled(config('services.kadi_api.url'));
    }

    /**
     * Swap a launch token (issued with the site key) for a 12-hour sportsbook player token. Never retried: after a
     * timeout the launch token may be spent, so the caller starts again with a fresh one.
     */
    public function redeem(string $launchToken): WagerResponse
    {
        return $this->send(fn () => $this->http()->post('launch-tokens/redeem', ['token' => $launchToken]), 'redeem');
    }

    /**
     * POST /wagers: the stake (or a free bet) for one wager. Idempotent by product and reference.
     *
     * @param  array{product: string, reference: string, funding: string, stake?: string, free_bet_amount?: string, channel?: string}  $body
     */
    public function place(string $playerToken, array $body): WagerResponse
    {
        return $this->send(fn () => $this->http()->withToken($playerToken)->post('wagers', $body), 'place');
    }

    /**
     * @param  array{product: string, version: int, outcome: string, payout: string, bonus?: string, reason?: string}  $body
     */
    public function settle(string $reference, array $body): WagerResponse
    {
        return $this->send(fn () => $this->http()->post('wagers/'.rawurlencode($reference).'/settle', $body), 'settle');
    }

    /**
     * @param  array{product: string, version: int, reason: string}  $body
     */
    public function reverse(string $reference, array $body): WagerResponse
    {
        return $this->send(fn () => $this->http()->post('wagers/'.rawurlencode($reference).'/reverse', $body), 'reverse');
    }

    /**
     * GET /wagers/{reference}: the wager and every settlement version, to reconcile after an unknown outcome.
     */
    public function lookup(string $product, string $reference): WagerResponse
    {
        return $this->send(fn () => $this->http()->get('wagers/'.rawurlencode($reference), ['product' => $product]), 'lookup');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.kadi_api.url'))
            ->withHeaders(['X-API-KEY' => (string) config('services.kadi_api.sportsbook_game_key')])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('sportsbook.wagers.timeout_seconds', 10))
            ->connectTimeout(5);
    }

    /**
     * @param  callable(): Response  $call
     */
    private function send(callable $call, string $action): WagerResponse
    {
        if (! $this->configured()) {
            Log::error('Sportsbook wagers: the Kadi API game key is not set', ['action' => $action]);

            return new WagerResponse(503, 'not_configured');
        }

        try {
            $response = $call();
        } catch (ConnectionException) {
            Log::warning('Sportsbook wagers: no answer from the Kadi API', ['action' => $action]);

            return WagerResponse::noAnswer();
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->successful()) {
            $data = $body['data'] ?? [];

            return new WagerResponse($response->status(), null, is_array($data) ? $data : []);
        }

        $code = is_string($body['code'] ?? null) ? $body['code'] : null;

        if ($response->status() >= 500 || $response->status() === 401) {
            Log::warning('Sportsbook wagers: the Kadi API refused a call', ['action' => $action, 'status' => $response->status(), 'code' => $code]);
        }

        unset($body['message']);

        return new WagerResponse($response->status(), $code ?? 'http_'.$response->status(), $body);
    }
}
