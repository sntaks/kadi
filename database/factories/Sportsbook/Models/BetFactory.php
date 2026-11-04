<?php

namespace Database\Factories\Sportsbook\Models;

use App\Models\User;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\BetType;
use App\Sportsbook\Enums\OddsChangePolicy;
use App\Sportsbook\Models\Bet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An open KES 100 single at 2.00 with no legs and no money moved. Real bets go through App\Sportsbook\Betting\PlaceBet.
 *
 * @extends Factory<Bet>
 */
class BetFactory extends Factory
{
    protected $model = Bet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference' => Str::upper(Str::random(8)),
            'type' => BetType::Single,
            'status' => BetStatus::Open,
            'stake' => 10_000,
            'total_odds' => '2.00',
            'potential_payout' => 20_000,
            'odds_change_policy' => OddsChangePolicy::Higher,
            'placed_at' => now(),
        ];
    }
}
