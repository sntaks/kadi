<?php

namespace App\Games;

use App\Account\ResponsiblePlay;
use App\Models\GameLaunch;
use App\Models\User;
use App\Services\KadiApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
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
        private readonly ResponsiblePlay $responsiblePlay,
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
            // A break covers every game. KadiApi refuses token launches itself (player_excluded); Kadi's
            // ggid form never asks the API, so for Kadi the site checks first.
            if ($game->usesGgid() && $this->responsiblePlay->onBreak($user)) {
                throw new LaunchFailed('on_break');
            }

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

        // Before the token is issued: a launch token is single-use and never retried, so don't spend one on a
        // launch that can't carry the account id (keys missing or malformed, or an id out of range).
        try {
            $accountId = encrypt_account_id((string) $user->linked_id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            Log::error('Game launch refused: the account id cannot be encrypted', ['game' => $game->code, 'error' => class_basename($e)]);

            throw new LaunchFailed('not_configured');
        }

        try {
            $issued = $this->api->issueLaunchToken((int) $user->linked_id, $game->code, $ip);
        } catch (RequestException $e) {
            $reason = match (true) {
                $e->response->status() === 429 => 'rate_limited',
                $e->response->json('code') === 'player_inactive' => 'player_inactive',
                $e->response->json('code') === 'player_excluded' => 'on_break',
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
            // The title's key (the code without its slots_/scratch_ prefix): the slot and scratch servers read the title from it.
            'current_game' => $title?->key,
            // The player's KadiApi customer id, encrypted and signed for the game server (random IV: changes per launch).
            'account_id' => $accountId,
        ]));
    }

    private function record(User $user, Game $game, ?GameTitle $title, string $status, ?string $error = null): void
    {
        GameLaunch::query()->create([
            'user_id' => $user->id,
            'game' => $game->code,
            // The title's key, not the API code, so "Most played" survives a prefix the API adds or drops.
            'product' => $title?->key,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
