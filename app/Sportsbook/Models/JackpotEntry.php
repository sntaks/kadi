<?php

namespace App\Sportsbook\Models;

use App\Models\User;
use App\Sportsbook\Enums\JackpotEntryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\JackpotEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A player's jackpot entry. Money is in cents and moves through the Kadi API wallet (SportsWallet, from Phase 8):
 * the entry price when it is made, a prize when the jackpot settles, or the price back if it is cancelled.
 *
 * @property int $id
 * @property int $jackpot_id
 * @property int $user_id
 * @property string $reference
 * @property JackpotEntryStatus $status
 * @property int $stake
 * @property int|null $correct_count
 * @property int|null $prize Paid after tax; the refund for a cancelled jackpot.
 * @property string|null $client_key
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['jackpot_id', 'user_id', 'reference', 'status', 'stake', 'correct_count', 'prize', 'client_key', 'placed_at', 'settled_at'])]
class JackpotEntry extends SportsbookModel
{
    protected $table = 'sb_jackpot_entries';

    /** @use HasFactory<JackpotEntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JackpotEntryStatus::class,
            'stake' => 'integer',
            'correct_count' => 'integer',
            'prize' => 'integer',
            'placed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Jackpot, $this>
     */
    public function jackpot(): BelongsTo
    {
        return $this->belongsTo(Jackpot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<JackpotPick, $this>
     */
    public function picks(): HasMany
    {
        return $this->hasMany(JackpotPick::class);
    }
}
