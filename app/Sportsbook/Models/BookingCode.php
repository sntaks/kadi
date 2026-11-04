<?php

namespace App\Sportsbook\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Sportsbook\Models\BookingCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shareable bet slip: a short code standing for a list of selections. Expires when the first match kicks off.
 *
 * @property int $id
 * @property string $code
 * @property list<int> $selection_ids
 * @property int|null $created_by_user_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['code', 'selection_ids', 'created_by_user_id', 'expires_at'])]
class BookingCode extends SportsbookModel
{
    protected $table = 'sb_booking_codes';

    /** @use HasFactory<BookingCodeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selection_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
