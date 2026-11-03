<?php

namespace App\Games;

use RuntimeException;

/**
 * A launch the player can't start. `reason` is a stable code for the launch log;
 * the message is safe to show the player.
 */
class LaunchFailed extends RuntimeException
{
    public const MESSAGES = [
        'not_linked' => 'Your account is still being set up. Try again in a minute.',
        'player_inactive' => "Your account can't play right now. Contact support and we'll help.",
        'game_unavailable' => 'This game is closed right now. Try another one.',
        'rate_limited' => 'Too many games opened at once. Wait a minute and try again.',
        'unreachable' => "We couldn't reach the game. Try again.",
        'bad_launch_url' => "This game can't be opened safely right now. Try another one.",
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::MESSAGES[$reason] ?? self::MESSAGES['unreachable']);
    }
}
