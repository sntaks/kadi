<?php

namespace App\Http\Controllers;

use App\Games\GameLauncher;
use App\Games\LaunchFailed;
use App\Games\LaunchHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Starts a game in two steps (post/redirect/get), so Back never re-posts a launch:
 *
 * 1. POST /play/{game}: the launcher issues the launch and LaunchHandoff keeps it
 *    (encrypted, one read, two minutes) under a fresh handle.
 * 2. GET /play/{game}/open/{handoff}: reads it once and posts it on to the game.
 *    Opening it again (Back, refresh) says the game was closed.
 *
 * The play page targets these at its frame (or a new tab, or the whole page for
 * games not framed yet). The token is never in the play page, a log, the session,
 * the database or the browser history.
 */
class GameLaunchController extends Controller
{
    public function store(Request $request, GameLauncher $launcher, LaunchHandoff $handoffs, string $game): RedirectResponse|Response
    {
        $found = $launcher->game($game);

        if ($found === null) {
            return self::page(['error' => LaunchFailed::MESSAGES['game_unavailable'], 'game' => null], 404);
        }

        $title = null;

        if ($found->hasTitles()) {
            $product = $request->input('product');
            $title = is_string($product) ? $found->title($product) : null;

            if ($title === null) {
                return self::page(['error' => LaunchFailed::MESSAGES['game_unavailable'], 'game' => $found], 404);
            }
        }

        try {
            $form = $launcher->launch($request->user(), $found, $title, $request->ip());
            $handoff = ['action' => $form->action, 'fields' => $form->fields];
        } catch (LaunchFailed $e) {
            $handoff = ['error' => $e->getMessage()];
        }

        $handle = $handoffs->put($request->user(), $found->code, $handoff);

        return redirect()->route('play.open', ['game' => $found->code, 'handoff' => $handle], 303);
    }

    public function open(Request $request, GameLauncher $launcher, LaunchHandoff $handoffs, string $game, string $handoff): Response
    {
        $found = $launcher->game($game);
        $stored = $found ? $handoffs->pull($request->user(), $found->code, $handoff) : null;

        if ($stored === null) {
            // Already used (Back, refresh), expired, or not this player's.
            return self::page(['used' => true, 'game' => $found], 410);
        }

        return isset($stored['error'])
            ? self::page(['error' => $stored['error'], 'game' => $found], 422)
            : self::page(['action' => $stored['action'], 'fields' => $stored['fields'], 'game' => $found]);
    }

    /** @param  array<string, mixed>  $data */
    public static function page(array $data, int $status = 200): Response
    {
        return response()->view('games.handoff', $data, $status)->withHeaders([
            'Cache-Control' => 'no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            // Only our own play page may frame this page.
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
