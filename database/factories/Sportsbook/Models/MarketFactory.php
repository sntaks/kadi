<?php

namespace Database\Factories\Sportsbook\Models;

use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    protected $model = Market::class;

    /**
     * Define the model's default state: an open, freshly priced 1X2 market.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fixture_id' => Fixture::factory(),
            'type' => MarketType::MatchResult,
            'line' => null,
            'key' => Market::keyFor(MarketType::MatchResult),
            'status' => MarketStatus::Open,
            'suspension_reason' => null,
            'bookmaker_count' => 5,
            'priced_at' => now(),
        ];
    }

    /**
     * Indicate that the market is suspended for the given reason.
     */
    public function suspended(SuspensionReason $reason = SuspensionReason::Manual): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MarketStatus::Suspended,
            'suspension_reason' => $reason,
        ]);
    }

    /**
     * Indicate that the market was last priced the given number of hours ago.
     */
    public function pricedHoursAgo(int $hours): static
    {
        return $this->state(fn (array $attributes) => [
            'priced_at' => now()->subHours($hours),
        ]);
    }
}
