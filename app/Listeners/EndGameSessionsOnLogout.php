<?php

namespace App\Listeners;

use App\Jobs\RevokeGameSessions;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

/**
 * Logging out of the site also ends the player's game sessions, so a shared
 * phone left on a slot can't keep playing on their wallet. Only when games are
 * launched with API tokens (Kadi's own form has no API session to end).
 */
class EndGameSessionsOnLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! config('platform.token_launch') || ! $user instanceof User || ! $user->linked_id) {
            return;
        }

        RevokeGameSessions::dispatch((int) $user->linked_id);
    }
}
