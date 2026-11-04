<?php

namespace Database\Factories\Sportsbook\Models;

use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Fixture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open home-win leg at 2.00 on a fixture of its own.
 *
 * @extends Factory<BetLeg>
 */
class BetLegFactory extends Factory
{
    protected $model = BetLeg::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bet_id' => Bet::factory(),
            'fixture_id' => Fixture::factory(),
            'fixture_name' => 'Arsenal v Chelsea',
            'kicks_off_at' => now()->addDay(),
            'market_type' => MarketType::MatchResult,
            'market_line' => null,
            'market_name' => '1X2',
            'outcome' => 'home',
            'selection_name' => 'Arsenal',
            'price' => '2.00',
            'status' => BetLegStatus::Open,
        ];
    }
}
