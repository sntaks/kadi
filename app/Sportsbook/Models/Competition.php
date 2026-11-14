<?php

namespace App\Sportsbook\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A league or cup as listed by The Odds API (one "sport key" there, e.g. soccer_epl).
 *
 * @property int $id
 * @property int $sport_id
 * @property string $odds_api_key
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_active_in_feed
 * @property bool $is_synced
 * @property int $sort_order
 * @property bool $auto_settle Results come from the scores feed; otherwise from the admin panel.
 * @property CarbonImmutable|null $scores_checked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['sport_id', 'odds_api_key', 'slug', 'name', 'description', 'is_active_in_feed', 'is_synced', 'sort_order', 'auto_settle', 'scores_checked_at'])]
class Competition extends SportsbookModel
{
    protected $table = 'sb_competitions';

    /** Pages under /sports that a competition's /sports/{slug} must never shadow. */
    /** Paths under /sports that are pages, not competitions: the sport pages (config sportsbook.sports) included. */
    public const RESERVED_SLUGS = ['match', 'jackpot', 'live', 'search', 'football', 'basketball', 'ice-hockey'];

    /** @use HasFactory<CompetitionFactory> */
    use HasFactory, LogsActivity;

    /**
     * Give new competitions a unique URL slug from their name. The slug never changes afterwards, so links keep
     * working when the feed renames a competition.
     */
    protected static function booted(): void
    {
        static::creating(function (self $competition): void {
            // Competitions listed as manual are settled from the admin panel instead of the scores feed.
            if ($competition->getAttribute('auto_settle') === null) {
                $competition->auto_settle = ! in_array($competition->odds_api_key, (array) config('sportsbook.settlement.manual_competitions'), true);
            }

            if (filled($competition->slug)) {
                return;
            }

            $slug = Str::slug($competition->name) ?: Str::slug($competition->odds_api_key);
            $taken = in_array($slug, self::RESERVED_SLUGS, true) || static::where('slug', $slug)->exists();
            $competition->slug = $taken ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active_in_feed' => 'boolean',
            'is_synced' => 'boolean',
            'auto_settle' => 'boolean',
            'scores_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function upcomingFixtures(): HasMany
    {
        return $this->fixtures()->upcoming();
    }

    /**
     * Competitions whose fixtures and odds are pulled from the feed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function synced(Builder $query): void
    {
        $query->where('is_synced', true)->where('is_active_in_feed', true);
    }

    /**
     * Record who switched syncing on or off; each switch changes what the scheduled sync spends.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('odds')
            ->logOnly(['is_synced', 'sort_order', 'auto_settle'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Competition {$eventName}");
    }
}
