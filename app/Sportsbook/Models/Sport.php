<?php

namespace App\Sportsbook\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\SportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'sort_order'])]
class Sport extends SportsbookModel
{
    protected $table = 'sb_sports';

    /** @use HasFactory<SportFactory> */
    use HasFactory;

    /**
     * @return HasMany<Competition, $this>
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }
}
