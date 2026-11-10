<?php

namespace Database\Factories\Sportsbook\Models;

use App\Sportsbook\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sport>
 */
class SportFactory extends Factory
{
    protected $model = Sport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'sort_order' => 100,
        ];
    }

    /** A sport from config/sportsbook.php `sports` (football, basketball), so it gets that sport's rules. */
    public function configured(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Str::headline($slug),
            'slug' => $slug,
            'sort_order' => (int) config("sportsbook.sports.{$slug}.sort_order", 100),
        ]);
    }
}
