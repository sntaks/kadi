<?php

namespace Database\Factories\Sportsbook\Models;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competition>
 */
class CompetitionFactory extends Factory
{
    protected $model = Competition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sport_id' => Sport::factory(),
            'odds_api_key' => 'soccer_'.fake()->unique()->lexify('????????'),
            'name' => fake()->words(2, true),
            'description' => null,
            'is_active_in_feed' => true,
            'is_synced' => false,
            'sort_order' => 100,
        ];
    }

    /**
     * Indicate that fixtures and odds are pulled from the feed for this competition.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_synced' => true,
        ]);
    }
}
