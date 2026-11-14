<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Matches in play whose score or live prices were refreshed by `sportsbook:sync-live`, for every open sports page on
 * the public `sports.live` channel (plan §4.2). Public match data only: no player, no stake, no bet.
 */
class LiveFixturesUpdated implements ShouldBroadcastNow
{
    /**
     * @param  list<array{id: int, home_score: int|null, away_score: int|null, markets: list<array{id: int, open: bool, selections: array<int, string|null>}>}>  $fixtures
     */
    public function __construct(public readonly array $fixtures) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('sports.live')];
    }

    public function broadcastAs(): string
    {
        return 'fixtures.updated';
    }

    /**
     * @return array{fixtures: list<array<string, mixed>>}
     */
    public function broadcastWith(): array
    {
        return ['fixtures' => $this->fixtures];
    }
}
