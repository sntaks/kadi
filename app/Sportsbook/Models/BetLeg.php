<?php

namespace App\Sportsbook\Models;

use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\MarketType;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\BetLegFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pick on a bet, with the match, market, pick and price copied as they were when the bet was placed.
 *
 * @property int $id
 * @property int $bet_id
 * @property int|null $selection_id
 * @property int|null $market_id
 * @property int $fixture_id
 * @property string $fixture_name
 * @property CarbonImmutable $kicks_off_at
 * @property MarketType $market_type
 * @property string|null $market_line
 * @property string $market_name
 * @property string $outcome
 * @property string $selection_name
 * @property string $price
 * @property BetLegStatus $status
 * @property string|null $void_reason Set when the leg was voided on its own (postponed, pricing error).
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['bet_id', 'selection_id', 'market_id', 'fixture_id', 'fixture_name', 'kicks_off_at', 'market_type', 'market_line', 'market_name', 'outcome', 'selection_name', 'price', 'status', 'void_reason', 'settled_at'])]
class BetLeg extends SportsbookModel
{
    protected $table = 'sb_bet_legs';

    /** @use HasFactory<BetLegFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kicks_off_at' => 'datetime',
            'market_type' => MarketType::class,
            'market_line' => 'decimal:2',
            'price' => 'decimal:2',
            'status' => BetLegStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Bet, $this>
     */
    public function bet(): BelongsTo
    {
        return $this->belongsTo(Bet::class);
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /**
     * @return BelongsTo<Selection, $this>
     */
    public function selection(): BelongsTo
    {
        return $this->belongsTo(Selection::class);
    }
}
