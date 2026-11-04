<?php

namespace Database\Factories\Sportsbook\Models;

use App\Models\User;
use App\Sportsbook\Enums\FreeBetSource;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Models\FreeBet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An available KES 100 free bet given by staff: total odds of at least 1.50, expiring in 7 days.
 *
 * @extends Factory<FreeBet>
 */
class FreeBetFactory extends Factory
{
    protected $model = FreeBet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 10_000,
            'min_odds' => '1.50',
            'expires_at' => now()->addDays(7),
            'status' => FreeBetStatus::Available,
            'source' => FreeBetSource::Admin,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function used(): static
    {
        return $this->state(fn (): array => ['status' => FreeBetStatus::Used, 'used_at' => now()]);
    }
}
