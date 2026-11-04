<?php

namespace App\Sportsbook\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One match in a jackpot. The outcome is "1", "X" or "2" once the full-time result is in; a void match counts as
 * right for every entry (decision 38).
 *
 * @property int $id
 * @property int $jackpot_id
 * @property int $fixture_id
 * @property int $position Numbered from 1 in kick-off order.
 * @property string $fixture_name
 * @property CarbonImmutable $kicks_off_at The kick-off when the jackpot was published.
 * @property string|null $outcome
 * @property bool $is_void
 * @property string|null $void_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['jackpot_id', 'fixture_id', 'position', 'fixture_name', 'kicks_off_at', 'outcome', 'is_void', 'void_reason'])]
class JackpotFixture extends SportsbookModel
{
    protected $table = 'sb_jackpot_fixtures';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'kicks_off_at' => 'datetime',
            'is_void' => 'boolean',
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
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /**
     * @return HasMany<JackpotPick, $this>
     */
    public function picks(): HasMany
    {
        return $this->hasMany(JackpotPick::class);
    }

    public function isDecided(): bool
    {
        return $this->is_void || $this->outcome !== null;
    }

    /**
     * Whether a pick is right: null while the match is undecided, always true when it is void.
     */
    public function judge(string $pick): ?bool
    {
        return match (true) {
            $this->is_void => true,
            $this->outcome === null => null,
            default => $this->outcome === $pick,
        };
    }
}
