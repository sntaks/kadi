<?php

namespace App\Sportsbook\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pick on a jackpot entry: "1" home win, "X" draw or "2" away win.
 *
 * @property int $id
 * @property int $jackpot_entry_id
 * @property int $jackpot_fixture_id
 * @property string $pick
 */
#[Fillable(['jackpot_entry_id', 'jackpot_fixture_id', 'pick'])]
class JackpotPick extends SportsbookModel
{
    protected $table = 'sb_jackpot_picks';

    public $timestamps = false;

    /**
     * @return BelongsTo<JackpotEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JackpotEntry::class, 'jackpot_entry_id');
    }

    /**
     * @return BelongsTo<JackpotFixture, $this>
     */
    public function jackpotFixture(): BelongsTo
    {
        return $this->belongsTo(JackpotFixture::class);
    }
}
