<?php

namespace App\Sportsbook\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One settlement or reversal waiting to reach the Kadi API, or already sent (plan §5.3). Written in the same database
 * transaction as the local decision it reports, and sent in id order per wager by App\Sportsbook\Wagers\WagerOps.
 *
 * Status: `pending` (waiting or retrying), `sent` (the API applied it, or had already), `failed` (refused, or out of
 * attempts: an operator decides, and nothing later for that wager is sent until then).
 *
 * @property int $id
 * @property string $product
 * @property string $wager_reference
 * @property string $subject_type "bet" or "jackpot_entry"
 * @property int $subject_id
 * @property string $operation "settle" or "reverse"
 * @property int $version
 * @property array<string, mixed> $payload The request body.
 * @property string $fingerprint
 * @property string $status
 * @property int $attempts
 * @property CarbonImmutable|null $next_attempt_at
 * @property int|null $last_status The API's HTTP status on the last attempt (null: no answer).
 * @property string|null $last_error The API's refusal code on the last attempt.
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['product', 'wager_reference', 'subject_type', 'subject_id', 'operation', 'version', 'payload', 'fingerprint', 'status', 'attempts', 'next_attempt_at', 'last_status', 'last_error', 'sent_at'])]
class WagerOp extends SportsbookModel
{
    public const SETTLE = 'settle';

    public const REVERSE = 'reverse';

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $table = 'sb_wager_ops';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'subject_id' => 'integer',
            'version' => 'integer',
            'attempts' => 'integer',
            'last_status' => 'integer',
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** The bet or jackpot entry this op settles. */
    public function subject(): Bet|JackpotEntry|null
    {
        /** @var class-string<Model> $class */
        $class = $this->subject_type === 'bet' ? Bet::class : JackpotEntry::class;

        /** @var Bet|JackpotEntry|null */
        return $class::query()->find($this->subject_id);
    }

    /**
     * Ops for an operator: failed ones, and pending ones older than the alert time.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function needsOperator(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('status', self::FAILED)
            ->orWhere(fn (Builder $query) => $query
                ->where('status', self::PENDING)
                ->where('created_at', '<', now('UTC')->subMinutes((int) config('sportsbook.wagers.pending_alert_minutes')))));
    }
}
