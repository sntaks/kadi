<?php

namespace App\Sportsbook\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\OddsApiCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Ledger entry for one call to The Odds API, with the quota headers it returned.
 *
 * @property int $id
 * @property string $endpoint
 * @property string|null $sport_key
 * @property int $credits_spent
 * @property int|null $credits_remaining
 * @property int|null $credits_used
 * @property int|null $http_status
 * @property bool $succeeded
 * @property string|null $error
 * @property CarbonImmutable $created_at
 */
#[Fillable(['endpoint', 'sport_key', 'credits_spent', 'credits_remaining', 'credits_used', 'http_status', 'succeeded', 'error', 'created_at'])]
class OddsApiCall extends SportsbookModel
{
    protected $table = 'sb_odds_api_calls';

    /** @use HasFactory<OddsApiCallFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
