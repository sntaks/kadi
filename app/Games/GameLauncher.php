<?php

namespace App\Games;

use App\Models\GameLaunch;
use App\Models\User;
use App\Services\KadiApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * The one way to start a game. Kadi keeps its existing `ggid` form (the player's
 * account_no posted to the Kadi play URL); every other game gets a single-use
 * Kadi API launch token posted to its launch URL with the chosen title.
 *
 * Tokens only ever travel in the returned form: never in a URL, a log line, the
 * database, the session or the play page itself (LaunchHandoff keeps the form
 * encrypted in the cache for one read).
 */
class GameLauncher
{
    public function __construct(
        private readonly GameCatalogue $catalogue,
        private readonly KadiApiService $api,
    ) {}

    /**
     * The game a player may open: from the catalogue, or Kadi from config when the catalogue
     * is unavailable (Kadi launches never needed the API).
     */
    public function game(string $code): ?Game
    {
        $game = $this->catalogue->game($code);

        if ($game !== null || $code !== 'kadi') {
            return $game;
        }

        $playUrl = (string) config('services.kadi_api.play_url');

        return GameCatalogue::isAllowedLaunchUrl($playUrl)
            ? new Game('kadi', 'Kadi', $playUrl, 1, config('games.games.kadi.tagline'), ['kadi'], [])
            : null;
    }

    /** @throws LaunchFailed */
    public function launch(User $user, Game $game, ?GameTitle $title, ?string $ip): LaunchForm
    {
        try {
            $form = $game->usesGgid() ? $this->ggidForm($user) : $this->tokenForm($user, $game, $title, $ip);
        } catch (LaunchFailed $e) {
            $this->record($user, $game, $title, 'failed', $e->reason);

            throw $e;
        }

        $this->record($user, $game, $title, 'launched');

        return $form;
    }

    private function ggidForm(User $user): LaunchForm
    {
        $playUrl = (string) config('services.kadi_api.play_url');

        if (! $user->account_no) {
            throw new LaunchFailed('not_linked');
        }

        if (! GameCatalogue::isAllowedLaunchUrl($playUrl)) {
            throw new LaunchFailed('bad_launch_url');
        }

        return new LaunchForm($playUrl, ['ggid' => (string) $user->account_no]);
    }

    private function tokenForm(User $user, Game $game, ?GameTitle $title, ?string $ip): LaunchForm
    {
        if (! $user->linked_id) {
            throw new LaunchFailed('not_linked');
        }

        try {
            $issued = $this->api->issueLaunchToken((int) $user->linked_id, $game->code, $ip);
        } catch (RequestException $e) {
            $reason = match (true) {
                $e->response->status() === 429 => 'rate_limited',
                $e->response->json('code') === 'player_inactive' => 'player_inactive',
                $e->response->status() === 404 && $e->response->json('message') === 'Customer not found' => 'not_linked',
                $e->response->json('code') === 'game_unavailable', $e->response->status() === 404 => 'game_unavailable',
                default => 'unreachable',
            };

            Log::warning('Game launch refused by the Kadi API', ['game' => $game->code, 'status' => $e->response->status(), 'reason' => $reason]);

            throw new LaunchFailed($reason);
        } catch (ConnectionException|UnexpectedValueException $e) {
            Log::warning('Game launch failed: Kadi API unavailable', ['game' => $game->code, 'error' => class_basename($e)]);

            throw new LaunchFailed('unreachable');
        }

        // The API names the URL; it must still be one of ours before a token is posted to it.
        if (! GameCatalogue::isAllowedLaunchUrl($issued['launch_url'])) {
            Log::warning('Game launch refused: launch URL not on an allowed host', ['game' => $game->code]);

            throw new LaunchFailed('bad_launch_url');
        }

        return new LaunchForm($issued['launch_url'], array_filter([
            'token' => $issued['token'],
            'product' => $title?->code,
        ]));
    }

    private function record(User $user, Game $game, ?GameTitle $title, string $status, ?string $error = null): void
    {
        GameLaunch::query()->create([
            'user_id' => $user->id,
            'game' => $game->code,
            'product' => $title?->code,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
