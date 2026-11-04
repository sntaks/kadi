<?php

namespace Database\Factories\Sportsbook\Models;

use App\Sportsbook\Models\BookingCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingCode>
 */
class BookingCodeFactory extends Factory
{
    protected $model = BookingCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(6)),
            'selection_ids' => [],
            'created_by_user_id' => null,
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * Indicate that the first match on the slip has already kicked off.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
