<?php

namespace App\Sportsbook\Wagers;

use App\Models\User;
use App\Sportsbook\Jobs\SendWagerOps;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\WagerOp;
use App\Sportsbook\Support\Money;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The outbox of settlements and reversals for the Kadi API (plan §5.3).
 *
 * Settling, reopening and cancelling decide locally and call settle()/reverse() inside their own database transaction,
 * so an op exists exactly when the decision does. After the commit, sendDue() posts the ops in id order per wager:
 *
 * - 200/201: sent (a 200 replay means the API already had it);
 * - no answer, 5xx, 429, or 409 version_out_of_order: tried again with backoff, then left for an operator;
 * - any other refusal: failed at once, for an operator.
 *
 * A failed op holds back every later op of the same wager, so versions always reach the API in order.
 */
class WagerOps
{
    private const SENDER_LOCK = 'sportsbook.wager-ops.sender';

    public function __construct(private KadiWagerClient $client) {}

    /**
     * @param  'won'|'lost'|'void'  $outcome
     * @param  int  $payout  Cents. A cash win includes the stake; a free bet's win excludes the voucher. 0 for lost and void.
     * @param  int  $bonus  Cents.
     */
    public function settle(Bet|JackpotEntry $subject, int $version, string $outcome, int $payout, int $bonus = 0, ?string $reason = null): WagerOp
    {
        return $this->record($subject, WagerOp::SETTLE, $version, array_filter([
            'version' => $version,
            'outcome' => $outcome,
            'payout' => Money::decimal($payout),
            'bonus' => $bonus > 0 ? Money::decimal($bonus) : null,
            'reason' => $reason === null ? null : mb_substr($reason, 0, 255),
        ], fn (mixed $value): bool => $value !== null));
    }

    public function reverse(Bet|JackpotEntry $subject, int $version, string $reason): WagerOp
    {
        return $this->record($subject, WagerOp::REVERSE, $version, [
            'version' => $version,
            'reason' => mb_substr($reason, 0, 255),
        ]);
    }

    /**
     * Whether any settlement of this player's bets or jackpot entries has not reached the API yet.
     */
    public function hasUnsentFor(User $user): bool
    {
        return WagerOp::query()
            ->where('status', '!=', WagerOp::SENT)
            ->where(fn (EloquentBuilder $query) => $query
                ->where(fn (EloquentBuilder $query) => $query->where('subject_type', 'bet')->whereIn('subject_id', Bet::query()->where('user_id', $user->id)->select('id')))
                ->orWhere(fn (EloquentBuilder $query) => $query->where('subject_type', 'jackpot_entry')->whereIn('subject_id', JackpotEntry::query()->where('user_id', $user->id)->select('id'))))
            ->exists();
    }

    /**
     * Send the ops written so far once the current transaction commits. Settling actions call it once after
     * writing all their ops (a jackpot writes one per entry); the scheduler sends anything missed within a minute.
     */
    public function sendAfterCommit(): void
    {
        SendWagerOps::dispatch()->afterCommit();
    }

    /**
     * Send the ops that are due, oldest first, one sender at a time.
     *
     * @return array{sent: int, retrying: int, failed: int}
     */
    public function sendDue(int $limit = 200, int $seconds = 45): array
    {
        $counts = ['sent' => 0, 'retrying' => 0, 'failed' => 0];
        $lock = Cache::lock(self::SENDER_LOCK, $seconds + 30);

        if (! $lock->get()) {
            return $counts;
        }

        try {
            $deadline = microtime(true) + $seconds;
            $taken = 0;

            // Batch after batch: sending an op can make the next op of the same wager due (a reverse, then the
            // settle of the next version). A batch that sends nothing ends the run; what is left retries later.
            while ($taken < $limit && microtime(true) < $deadline) {
                $sentBefore = $counts['sent'];
                $batch = $this->due($limit - $taken);

                foreach ($batch as $op) {
                    if (microtime(true) > $deadline) {
                        break 2;
                    }

                    $counts[$this->send($op)]++;
                    $taken++;
                }

                if ($counts['sent'] === $sentBefore) {
                    break;
                }
            }
        } finally {
            $lock->release();
        }

        return $counts;
    }

    /**
     * Put a failed op back in line, after an operator dealt with the cause.
     */
    public function retry(WagerOp $op): void
    {
        if ($op->status !== WagerOp::FAILED) {
            return;
        }

        $op->update(['status' => WagerOp::PENDING, 'attempts' => 0, 'next_attempt_at' => null]);
        $this->sendAfterCommit();
    }

    public static function subjectType(Bet|JackpotEntry $subject): string
    {
        return $subject instanceof Bet ? 'bet' : 'jackpot_entry';
    }

    public static function product(Bet|JackpotEntry $subject): string
    {
        return (string) config('sportsbook.wagers.products.'.self::subjectType($subject));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function record(Bet|JackpotEntry $subject, string $operation, int $version, array $body): WagerOp
    {
        if ($subject->wager_reference === null) {
            throw new LogicException("{$operation} for a ".self::subjectType($subject)." with no wager reference ({$subject->id}).");
        }

        $product = self::product($subject);
        $body = ['product' => $product, ...$body];
        $fingerprint = hash('sha256', $operation.'|'.json_encode($body, JSON_THROW_ON_ERROR));

        $op = WagerOp::query()->firstOrCreate(
            ['product' => $product, 'wager_reference' => $subject->wager_reference, 'operation' => $operation, 'version' => $version],
            [
                'subject_type' => self::subjectType($subject),
                'subject_id' => $subject->id,
                'payload' => $body,
                'fingerprint' => $fingerprint,
                'status' => WagerOp::PENDING,
            ],
        );

        // The same version may only ever mean one thing: a different body is a bug in the caller, never sent.
        if ($op->fingerprint !== $fingerprint) {
            throw new LogicException("A different {$operation} of version {$version} was already recorded for wager op {$op->id}.");
        }

        return $op;
    }

    /**
     * Pending ops that are due and have no unsent op before them for the same wager.
     *
     * @return Collection<int, WagerOp>
     */
    private function due(int $limit): Collection
    {
        return WagerOp::query()
            ->where('status', WagerOp::PENDING)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now('UTC')))
            ->whereNotExists(fn (Builder $query) => $query
                ->from('sb_wager_ops as earlier')
                ->whereColumn('earlier.wager_reference', 'sb_wager_ops.wager_reference')
                ->whereColumn('earlier.product', 'sb_wager_ops.product')
                ->whereColumn('earlier.id', '<', 'sb_wager_ops.id')
                ->where('earlier.status', '!=', WagerOp::SENT))
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return 'sent'|'retrying'|'failed'
     */
    private function send(WagerOp $op): string
    {
        $body = $op->payload;
        $response = $op->operation === WagerOp::SETTLE
            ? $this->client->settle($op->wager_reference, $body)
            : $this->client->reverse($op->wager_reference, $body);

        $attempts = $op->attempts + 1;

        if ($response->ok()) {
            DB::transaction(function () use ($op, $response, $attempts): void {
                $op->update(['status' => WagerOp::SENT, 'attempts' => $attempts, 'last_status' => $response->status, 'last_error' => null, 'sent_at' => now('UTC'), 'next_attempt_at' => null]);

                if ($op->operation === WagerOp::SETTLE) {
                    $this->recordTax($op, $response);
                }
            });

            return 'sent';
        }

        $maxAttempts = (int) config('sportsbook.wagers.op_max_attempts', 12);

        if ($response->retryable() && $attempts < $maxAttempts) {
            $backoff = (array) config('sportsbook.wagers.op_backoff', [60]);
            $delay = (int) ($backoff[min($attempts - 1, count($backoff) - 1)] ?? 60);
            $op->update(['attempts' => $attempts, 'last_status' => $response->status, 'last_error' => $response->code, 'next_attempt_at' => now('UTC')->addSeconds($delay)]);

            return 'retrying';
        }

        $op->update(['status' => WagerOp::FAILED, 'attempts' => $attempts, 'last_status' => $response->status, 'last_error' => $response->code, 'next_attempt_at' => null]);

        Log::error('Sportsbook wagers: an op needs an operator', [
            'op' => $op->id,
            'operation' => $op->operation,
            'version' => $op->version,
            'status' => $response->status,
            'code' => $response->code,
        ]);

        return 'failed';
    }

    /**
     * Keep the winnings tax the API withheld on the bet or entry, while this settlement is still its current one.
     */
    private function recordTax(WagerOp $op, WagerResponse $response): void
    {
        $tax = $response->data['settlement']['tax'] ?? null;
        $subject = $op->subject();

        if (! is_numeric($tax) || $subject === null) {
            return;
        }

        $current = $subject instanceof Bet ? $subject->wagerVersion() : $subject->jackpot->settlement_version;

        if ($current === $op->version && $subject->status->isSettled()) {
            $subject->update(['tax' => Money::fromAmount($tax)]);
        }
    }
}
