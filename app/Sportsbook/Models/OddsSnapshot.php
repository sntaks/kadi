<?php

namespace App\Sportsbook\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\OddsSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A price a selection was offered at, recorded whenever the offered price changes.
 *
 * @property int $id
 * @property int $selection_id
 * @property string $price
 * @property string|null $fair_probability
 * @property string $source "feed" or "override"
 * @property CarbonImmutable $captured_at
 */
#[Fillable(['selection_id', 'price', 'fair_probability', 'source', 'captured_at'])]
class OddsSnapshot extends SportsbookModel
{
    protected $table = 'sb_odds_snapshots';

    /** @use HasFactory<OddsSnapshotFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'fair_probability' => 'decimal:6',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Selection, $this>
     */
    public function selection(): BelongsTo
    {
        return $this->belongsTo(Selection::class);
    }
}
