<?php

namespace Database\Factories\Sportsbook\Models;

use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Jackpot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft KES 99 jackpot with no matches, and two prize tiers sized for three matches: KES 10,000 for 3 right and
 * KES 1,000 for 2. Add matches with withMatches().
 *
 * @extends Factory<Jackpot>
 */
class JackpotFactory extends Factory
{
    protected $model = Jackpot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Weekend', 'Midweek', 'Mega']).' Jackpot',
            'status' => JackpotStatus::Draft,
            'entry_price' => 9_900,
            'prize_tiers' => [['correct' => 3, 'pool' => 1_000_000], ['correct' => 2, 'pool' => 100_000]],
            'void_limit' => 1,
        ];
    }

    /**
     * Published and taking entries.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JackpotStatus::Open,
            'published_at' => now(),
        ]);
    }

    /**
     * With this many upcoming matches in one competition, an hour apart from two days ahead.
     */
    public function withMatches(int $count = 3): static
    {
        return $this->afterCreating(function (Jackpot $jackpot) use ($count): void {
            $competition = Competition::factory()->synced()->create();

            for ($position = 1; $position <= $count; $position++) {
                $fixture = Fixture::factory()->for($competition)->create(['kicks_off_at' => now()->addDays(2)->addHours($position)]);

                $jackpot->jackpotFixtures()->create([
                    'fixture_id' => $fixture->id,
                    'position' => $position,
                    'fixture_name' => $fixture->name(),
                    'kicks_off_at' => $fixture->kicks_off_at,
                ]);
            }
        });
    }
}
