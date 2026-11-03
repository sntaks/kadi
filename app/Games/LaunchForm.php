<?php

namespace App\Games;

/**
 * The form that hands a player to a game: posted (never put in a URL) to the
 * game's launch URL, into the play page's frame or a new tab.
 */
final readonly class LaunchForm
{
    /** @param  array<string, string>  $fields */
    public function __construct(
        public string $action,
        public array $fields,
    ) {}
}
