<?php

namespace App\Sportsbook\Models;

use App\Models\User;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\BetType;
use App\Sportsbook\Enums\OddsChangePolicy;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\BetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A placed bet. Money is in cents. The stake and any payout move through the Kadi API wallet (SportsWallet, from
 * Phase 8), never a local ledger.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $free_bet_id Placed with this free bet: no money left the wallet for the stake.
 * @property string $reference
 * @property BetType $type
 * @property BetStatus $status
 * @property int $stake
 * @property string $total_odds
 * @property int $potential_payout Capped at the maximum payout. Includes the multibet bonus; for a free bet, only the
 *                                 winnings above the stake.
 * @property int $bonus_percent The multibet bonus the bet qualified for when placed; 0 for none.
 * @property int|null $payout Cash paid, net of tax.
 * @property int|null $bonus Multibet bonus paid, before tax.
 * @property OddsChangePolicy $odds_change_policy
 * @property string|null $client_key
 * @property int $settlement_version
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'free_bet_id', 'reference', 'type', 'status', 'stake', 'total_odds', 'potential_payout', 'bonus_percent', 'payout', 'bonus', 'odds_change_policy', 'client_key', 'settlement_version', 'placed_at', 'settled_at'])]
class Bet extends SportsbookModel
{
    protected $table = 'sb_bets';

    /** @use HasFactory<BetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BetType::class,
            'status' => BetStatus::class,
            'stake' => 'integer',
            'total_odds' => 'decimal:2',
            'potential_payout' => 'integer',
            'bonus_percent' => 'integer',
            'payout' => 'integer',
            'bonus' => 'integer',
            'settlement_version' => 'integer',
            'odds_change_policy' => OddsChangePolicy::class,
            'placed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FreeBet, $this>
     */
    public function freeBet(): BelongsTo
    {
        return $this->belongsTo(FreeBet::class);
    }

    public function isFreeBet(): bool
    {
        return $this->free_bet_id !== null;
    }

    /**
     * Legs in the order they were added to the slip.
     *
     * @return HasMany<BetLeg, $this>
     */
    public function legs(): HasMany
    {
        return $this->hasMany(BetLeg::class)->orderBy('id');
    }
}
