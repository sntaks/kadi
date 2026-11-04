<?php

namespace App\Sportsbook\Models;

use App\Sportsbook\Enums\JackpotStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\JackpotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;

/**
 * A jackpot: players pay a fixed entry price to predict 1, X or 2 for every match. Each prize tier is a fixed pool
 * (cents) split equally between the entries with exactly that many right (decision 37). Entries close when the
 * first match kicks off.
 *
 * @property int $id
 * @property string $name
 * @property JackpotStatus $status
 * @property int $entry_price
 * @property list<array{correct: int, pool: int}> $prize_tiers Most correct first.
 * @property int $void_limit More void matches than this cancels the jackpot.
 * @property int $settlement_version
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $settled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'status', 'entry_price', 'prize_tiers', 'void_limit', 'settlement_version', 'cancel_reason', 'published_at', 'settled_at'])]
class Jackpot extends SportsbookModel
{
    protected $table = 'sb_jackpots';

    /** @use HasFactory<JackpotFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JackpotStatus::class,
            'entry_price' => 'integer',
            'prize_tiers' => 'array',
            'void_limit' => 'integer',
            'settlement_version' => 'integer',
            'published_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * The matches in kick-off order.
     *
     * @return HasMany<JackpotFixture, $this>
     */
    public function jackpotFixtures(): HasMany
    {
        return $this->hasMany(JackpotFixture::class)->orderBy('position');
    }

    /**
     * @return HasMany<JackpotEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(JackpotEntry::class);
    }

    /**
     * When entries close: the earliest current kick-off among its matches. Null with no matches yet.
     */
    public function closesAt(): ?CarbonImmutable
    {
        $kickOff = Fixture::query()
            ->whereIn('id', JackpotFixture::query()->where('jackpot_id', $this->id)->select('fixture_id'))
            ->min('kicks_off_at');

        // A raw database value: sb_ times are stored in UTC (SportsbookModel).
        return $kickOff === null ? null : Date::parse($kickOff, 'UTC')->toImmutable();
    }

    public function isTakingEntries(): bool
    {
        return $this->status === JackpotStatus::Open && ($this->closesAt()?->isFuture() ?? false);
    }

    /**
     * Every prize pool added up, in cents.
     */
    public function totalPrizes(): int
    {
        return (int) collect($this->prize_tiers)->sum('pool');
    }

    /**
     * The jackpot shown to players: the open one published last.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('status', JackpotStatus::Open)->latest('published_at')->latest('id');
    }
}
