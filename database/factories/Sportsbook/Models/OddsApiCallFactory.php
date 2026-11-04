<?php

namespace Database\Factories\Sportsbook\Models;

use App\Sportsbook\Models\OddsApiCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OddsApiCall>
 */
class OddsApiCallFactory extends Factory
{
    protected $model = OddsApiCall::class;

    /**
     * Define the model's default state: a successful paid odds call.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'endpoint' => 'odds',
            'sport_key' => 'soccer_epl',
            'credits_spent' => 1,
            'credits_remaining' => 400,
            'credits_used' => 100,
            'http_status' => 200,
            'succeeded' => true,
            'error' => null,
            'created_at' => now(),
        ];
    }
}
