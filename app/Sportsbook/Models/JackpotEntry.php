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
 * A player's jackpot entry. Money is in cents and moves through the Kadi API wallet as the wager `wager_reference`
 * (product sportsbook_jackpot): the entry price when it is made, a prize when the jackpot settles, or the price back
 * if it is cancelled.
 *
 * @property int $id
 * @property int $jackpot_id
 * @property int|null $user_id Null once the player closed their account.
 * @property string $reference
 * @property string|null $wager_reference The Kadi API wager reference (a UUID).
 * @property JackpotEntryStatus $status
 * @property string|null $reject_reason
 * @property int $stake
 * @property int|null $correct_count
 * @property int|null $prize Paid before tax; the refund for a cancelled jackpot.
 * @property int|null $tax Winnings tax the Kadi API withheld (null until it answers).
 * @property string|null $client_key
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['jackpot_id', 'user_id', 'reference', 'wager_reference', 'status', 'reject_reason', 'stake', 'correct_count', 'prize', 'tax', 'client_key', 'placed_at', 'settled_at'])]
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
            'tax' => 'integer',
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
