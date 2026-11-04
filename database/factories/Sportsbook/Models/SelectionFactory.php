<?php

namespace Database\Factories\Sportsbook\Models;

use App\Models\User;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Selection>
 */
class SelectionFactory extends Factory
{
    protected $model = Selection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market_id' => Market::factory(),
            'outcome' => 'home',
            'name' => fake()->city().' FC',
            'fair_probability' => '0.450000',
            'feed_price' => '2.09',
            'override_price' => null,
            'override_reason' => null,
            'override_by_admin_id' => null,
        ];
    }

    /**
     * Indicate that an admin has overridden the feed price.
     */
    public function overridden(string $price = '2.50'): static
    {
        return $this->state(fn (array $attributes) => [
            'override_price' => $price,
            'override_reason' => 'Team news',
            'override_by_admin_id' => User::factory(),
        ]);
    }
}
