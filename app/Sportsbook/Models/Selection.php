<?php

namespace App\Sportsbook\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\SelectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One outcome of a market. The offered price is the admin override when one is set, otherwise the feed price.
 *
 * @property int $id
 * @property int $market_id
 * @property string $outcome
 * @property string $name
 * @property string|null $fair_probability
 * @property string|null $feed_price
 * @property string|null $override_price
 * @property string|null $override_reason
 * @property int|null $override_by_admin_id
 * @property string|null $override_by_staff GMS staff member who set the override: "Name (GMS #id)".
 * @property string|null $previous_price
 * @property CarbonImmutable|null $price_changed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['market_id', 'outcome', 'name', 'fair_probability', 'feed_price', 'override_price', 'override_reason', 'override_by_admin_id', 'override_by_staff', 'previous_price', 'price_changed_at'])]
class Selection extends SportsbookModel
{
    protected $table = 'sb_selections';

    /** @use HasFactory<SelectionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fair_probability' => 'decimal:6',
            'feed_price' => 'decimal:2',
            'override_price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'price_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_admin_id');
    }

    /**
     * @return HasMany<OddsSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OddsSnapshot::class);
    }

    /**
     * The price players are offered.
     */
    public function price(): ?string
    {
        return $this->override_price ?? $this->feed_price;
    }

    /**
     * Record that the offered price may have moved from $offeredBefore: keep the old price for the odds-movement
     * arrow and add a history snapshot. Does nothing when the offered price is unchanged.
     */
    public function recordOfferedPriceChange(?string $offeredBefore): void
    {
        $offeredNow = $this->price();

        if ($offeredNow === null || $offeredNow === $offeredBefore) {
            return;
        }

        $this->forceFill(['previous_price' => $offeredBefore, 'price_changed_at' => now('UTC')])->save();

        $this->snapshots()->create([
            'price' => $offeredNow,
            'fair_probability' => $this->fair_probability,
            'source' => $this->hasOverride() ? 'override' : 'feed',
            'captured_at' => now('UTC'),
        ]);
    }

    /**
     * Whether the offered price moved within the last day, and which way.
     *
     * @return 'up'|'down'|null
     */
    public function recentMovement(): ?string
    {
        if ($this->previous_price === null || $this->price_changed_at === null || $this->price_changed_at->lt(now('UTC')->subDay())) {
            return null;
        }

        return match (true) {
            (float) $this->price() > (float) $this->previous_price => 'up',
            (float) $this->price() < (float) $this->previous_price => 'down',
            default => null,
        };
    }

    /**
     * The compact label printed on an odds button, e.g. "1", "X2", "Over" or "2-1".
     */
    public function shortLabel(): string
    {
        return match ($this->outcome) {
            'home' => '1',
            'draw' => 'X',
            'away' => '2',
            'home_draw' => '1X',
            'home_away' => '12',
            'draw_away' => 'X2',
            'over' => 'Over',
            'under' => 'Under',
            'yes' => 'Yes',
            'no' => 'No',
            'other' => 'Other',
            default => $this->outcome,
        };
    }

    public function hasOverride(): bool
    {
        return $this->override_price !== null;
    }
}
