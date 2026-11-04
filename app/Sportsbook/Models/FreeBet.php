<?php

namespace App\Sportsbook\Models;

use App\Models\User;
use App\Sportsbook\Enums\FreeBetSource;
use App\Sportsbook\Enums\FreeBetStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\FreeBetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A free bet: a stake the player did not pay for, used whole on one bet with total odds of at least min_odds before
 * it expires. A winning free bet pays the winnings above the stake, as cash; the stake itself never comes back.
 *
 * Expiry is not stored as a status: an available free bet past expires_at simply cannot be used.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount Cents.
 * @property string $min_odds
 * @property CarbonImmutable $expires_at
 * @property FreeBetStatus $status
 * @property FreeBetSource $source
 * @property string|null $note
 * @property int|null $admin_id
 * @property int|null $replaces_bet_id
 * @property string|null $void_reason
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $voided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'amount', 'min_odds', 'expires_at', 'status', 'source', 'note', 'admin_id', 'replaces_bet_id', 'void_reason', 'used_at', 'voided_at'])]
class FreeBet extends SportsbookModel
{
    protected $table = 'sb_free_bets';

    /** @use HasFactory<FreeBetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'min_odds' => 'decimal:2',
            'expires_at' => 'datetime',
            'status' => FreeBetStatus::class,
            'source' => FreeBetSource::class,
            'used_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The bet it was used on.
     *
     * @return HasOne<Bet, $this>
     */
    public function bet(): HasOne
    {
        return $this->hasOne(Bet::class);
    }

    /**
     * @return BelongsTo<Bet, $this>
     */
    public function replacesBet(): BelongsTo
    {
        return $this->belongsTo(Bet::class, 'replaces_bet_id');
    }

    public function isUsable(): bool
    {
        return $this->status === FreeBetStatus::Available && $this->expires_at->isFuture();
    }

    /**
     * What the player sees: available, used, expired or void.
     */
    public function state(): string
    {
        return $this->status === FreeBetStatus::Available && ! $this->expires_at->isFuture() ? 'expired' : $this->status->value;
    }

    public function stateLabel(): string
    {
        return $this->state() === 'expired' ? __('Expired') : $this->status->label();
    }

    /**
     * Free bets the player can use now, soonest to expire first.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->where('status', FreeBetStatus::Available)->where('expires_at', '>', now('UTC'))->orderBy('expires_at')->orderBy('id');
    }
}
