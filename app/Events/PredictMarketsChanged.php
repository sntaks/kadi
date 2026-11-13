<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Markets whose shown state moved (status, whether they take bets, the cut-off, the odds), sent by the feed worker
 * (`App\Predict\MarketFeed`) to every open prediction page on the public `predict.markets` channel. Public market
 * data only: no player, no stake, no reference. Broadcast synchronously: Reverb runs on the same box.
 */
class PredictMarketsChanged implements ShouldBroadcastNow
{
    /**
     * @param  list<array<string, mixed>>  $markets  `LiveState::derive()` records plus `changed_at`, at most one batch
     */
    public function __construct(public readonly array $markets) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('predict.markets')];
    }

    public function broadcastAs(): string
    {
        return 'markets.changed';
    }

    /**
     * @return array{markets: list<array<string, mixed>>}
     */
    public function broadcastWith(): array
    {
        return ['markets' => $this->markets];
    }
}
