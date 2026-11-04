<?php

namespace Database\Factories\Sportsbook\Models;

use App\Models\User;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\JackpotEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * An open KES 99 entry with no picks and no money moved. Real entries go through App\Sportsbook\Betting\Jackpot\EnterJackpot.
 *
 * @extends Factory<JackpotEntry>
 */
class JackpotEntryFactory extends Factory
{
    protected $model = JackpotEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jackpot_id' => Jackpot::factory()->open(),
            'user_id' => User::factory(),
            'reference' => 'J'.Str::upper(Str::random(7)),
            'wager_reference' => (string) Str::uuid(),
            'status' => JackpotEntryStatus::Open,
            'stake' => 9_900,
            'placed_at' => now(),
        ];
    }
}
