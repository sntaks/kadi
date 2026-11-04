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
 * A bet. Money is in cents. The stake and any payout move through the Kadi API wallet, never a local ledger: the bet
 * is the API wager `wager_reference` (product sportsbook), placed by PlaceBet and settled through sb_wager_ops.
 *
 * @property int $id
 * @property int|null $user_id Null once the player closed their account; the bet stays for the books.
 * @property int|null $free_bet_id Placed with this free bet: no money left the wallet for the stake.
 * @property string $reference Short, for support.
 * @property string|null $wager_reference The Kadi API wager reference (a UUID).
 * @property BetType $type
 * @property BetStatus $status
 * @property string|null $reject_reason The API's refusal code (or not_placed) for a rejected bet.
 * @property int $stake
 * @property string $total_odds
 * @property int $potential_payout Capped at the maximum payout. Includes the multibet bonus; for a free bet, only the
 *                                 winnings above the stake.
 * @property int $bonus_percent The multibet bonus the bet qualified for when placed; 0 for none.
 * @property int|null $payout What the settlement credits before tax: winnings plus bonus, or the stake back for a cash void.
 * @property int|null $bonus Multibet bonus paid, before tax.
 * @property int|null $tax Winnings tax the Kadi API withheld, from its settle response (null until it answers).
 * @property OddsChangePolicy $odds_change_policy
 * @property string|null $client_key
 * @property int $settlement_version
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'free_bet_id', 'reference', 'wager_reference', 'type', 'status', 'reject_reason', 'stake', 'total_odds', 'potential_payout', 'bonus_percent', 'payout', 'bonus', 'tax', 'odds_change_policy', 'client_key', 'settlement_version', 'placed_at', 'settled_at'])]
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
            'tax' => 'integer',
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

    /**
     * The version of the API settlement this bet is on: the first settlement is 1, each reopening adds one.
     */
    public function wagerVersion(): int
    {
        return $this->settlement_version + 1;
    }

    /** The winnings after the tax the API withheld. */
    public function netPayout(): ?int
    {
        return $this->payout === null ? null : $this->payout - ($this->tax ?? 0);
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
