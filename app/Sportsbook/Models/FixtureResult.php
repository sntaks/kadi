<?php

namespace App\Sportsbook\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\FixtureResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result bets on a fixture settle on. The feed gives the full-time score only; the half-time score (for
 * Half-time/Full-time) comes from an admin. A void result means the match was abandoned or never played.
 *
 * @property int $id
 * @property int $fixture_id
 * @property int|null $home_score
 * @property int|null $away_score
 * @property int|null $half_time_home_score
 * @property int|null $half_time_away_score
 * @property bool $is_void
 * @property string $source "feed" or "admin".
 * @property int|null $recorded_by_admin_id
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['fixture_id', 'home_score', 'away_score', 'half_time_home_score', 'half_time_away_score', 'is_void', 'source', 'recorded_by_admin_id', 'note'])]
class FixtureResult extends SportsbookModel
{
    protected $table = 'sb_fixture_results';

    /** @use HasFactory<FixtureResultFactory> */
    use HasFactory;

    public const SOURCE_FEED = 'feed';

    public const SOURCE_ADMIN = 'admin';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'home_score' => 'integer',
            'away_score' => 'integer',
            'half_time_home_score' => 'integer',
            'half_time_away_score' => 'integer',
            'is_void' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_admin_id');
    }

    public function hasHalfTimeScore(): bool
    {
        return $this->half_time_home_score !== null && $this->half_time_away_score !== null;
    }

    /**
     * "2-1", "2-1 (HT 1-0)" or "Void".
     */
    public function scoreLine(): string
    {
        if ($this->is_void) {
            return __('Void');
        }

        $score = "{$this->home_score}-{$this->away_score}";

        return $this->hasHalfTimeScore() ? $score.' '.__('(HT :score)', ['score' => "{$this->half_time_home_score}-{$this->half_time_away_score}"]) : $score;
    }
}
