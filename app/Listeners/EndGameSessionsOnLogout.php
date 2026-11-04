<?php

namespace App\Listeners;

use App\Jobs\RevokeGameSessions;
use App\Models\User;
use App\Sportsbook\Wagers\PlayerTokenBroker;
use Illuminate\Auth\Events\Logout;

/**
 * Logging out of the site also ends the player's game sessions, so a shared
 * phone left on a slot can't keep playing on their wallet: when games are
 * launched with API tokens, or the sportsbook holds a player token for them
 * (Kadi's own form has no API session to end). The sportsbook's cached token
 * is forgotten here, before the session it belongs to is invalidated.
 */
class EndGameSessionsOnLogout
{
    public function __construct(private PlayerTokenBroker $sportsbookTokens) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->sportsbookTokens->forget($user);

        if (! (config('platform.token_launch') || config('platform.sports_betting')) || ! $user->linked_id) {
            return;
        }

        RevokeGameSessions::dispatch((int) $user->linked_id);
    }
}
