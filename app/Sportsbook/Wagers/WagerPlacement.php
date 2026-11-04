<?php

namespace App\Sportsbook\Wagers;

use App\Account\ResponsiblePlay;
use App\Models\User;
use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Jobs\ReconcileWager;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Support\Money;
use App\Support\WalletBalanceWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Placing a saved, pending bet or jackpot entry as a Kadi API wager, and finding out what happened when the answer
 * never came (plan §5.3). The outcome is always the API's, never guessed:
 *
 * - 201, or a 200 replay: open, and the returned balance is written through the version-guarded writer;
 * - a clear refusal (insufficient_funds, player_excluded, stake limits…): rejected, no money moved, a free bet is
 *   given back;
 * - no answer, 5xx or 429: still pending; ReconcileWager looks the wager up. Found: open. Not found: a void
 *   tombstone is sent, so a late placement can never land, and it is rejected as not placed.
 */
class WagerPlacement
{
    public const REFUSED_NOT_ALLOWED = 'not_allowed';

    public const REFUSED_FUNDS = 'insufficient_funds';

    public const REFUSED_STAKE = 'stake';

    public const REFUSED_WALLET = 'wallet';

    /** How long after an unanswered placement the first lookup runs. */
    public const RECONCILE_DELAY_SECONDS = 30;

    /** How long a placement the lookup could not resolve waits before it is looked up again. */
    public const HOLD_MINUTES = 30;

    public function __construct(
        private KadiWagerClient $client,
        private PlayerTokenBroker $tokens,
        private WalletBalanceWriter $balances,
        private ResponsiblePlay $responsiblePlay,
        private SportsWallet $wallet,
    ) {}

    /**
     * Why this player cannot bet now, as [kind, message], or null when they can. The API checks again on every
     * wager; this only saves a round trip and gives a clear message.
     *
     * @return array{0: string, 1: string}|null
     */
    public function refusalFor(User $user): ?array
    {
        if (! config('platform.sports_betting')) {
            return [self::REFUSED_WALLET, __('Sports betting is not open yet.')];
        }

        if (! $user->hasVerifiedEmail()) {
            return [self::REFUSED_NOT_ALLOWED, __('Verify your e-mail address to place bets.')];
        }

        if (! $user->linked_id) {
            return self::refusal('not_linked');
        }

        if ($this->responsiblePlay->onBreak($user)) {
            return [self::REFUSED_NOT_ALLOWED, __('Betting is paused while your break from play runs.')];
        }

        return null;
    }

    /**
     * The player's sportsbook token, obtained before anything is saved.
     *
     * @return string|array{0: string, 1: string} The token, or why there is none as [kind, message].
     */
    public function token(User $user, ?string $ip = null): string|array
    {
        try {
            return $this->tokens->token($user, $ip);
        } catch (PlayerTokenUnavailable $e) {
            return self::refusal($e->reason);
        }
    }

    /**
     * Send a pending bet or entry, saved and committed, to the API. Returns it with its new status.
     */
    public function place(User $user, Bet|JackpotEntry $subject, string $token, ?string $ip = null): Bet|JackpotEntry
    {
        $body = $this->body($subject);
        $response = $this->client->place($token, $body);

        if ($response->invalidPlayerToken()) {
            // The token expired or was revoked: one fresh token, and the same reference, so nothing can be placed twice.
            try {
                $response = $this->client->place($this->tokens->renew($user, $ip), $body);
            } catch (PlayerTokenUnavailable $e) {
                return $this->reject($subject, $e->reason);
            }
        }

        if ($response->ok()) {
            $this->balances->writeFromResponse($user, $response->data['balance'] ?? null);

            return $this->open($subject);
        }

        if ($response->refused()) {
            return $this->reject($subject, $response->code ?? 'refused');
        }

        Log::warning('Sportsbook wagers: placement outcome unknown, looking it up', [
            'subject' => WagerOps::subjectType($subject),
            'id' => $subject->id,
            'status' => $response->status,
            'code' => $response->code,
        ]);

        ReconcileWager::dispatch(WagerOps::subjectType($subject), $subject->id)->delay(now()->addSeconds(self::RECONCILE_DELAY_SECONDS));

        return $subject->fresh() ?? $subject;
    }

    /**
     * Find out what became of a pending placement. True once it is resolved (open or rejected).
     */
    public function reconcile(Bet|JackpotEntry $subject): bool
    {
        $subject = $subject->fresh() ?? $subject;

        if (! $this->isPending($subject)) {
            return true;
        }

        $product = WagerOps::product($subject);
        $lookup = $this->client->lookup($product, (string) $subject->wager_reference);

        if ($lookup->ok()) {
            return match ($lookup->data['wager']['status'] ?? null) {
                'open' => $this->isOpen($this->open($subject)),
                // Voided at the API (by an admin, or our own tombstone): the stake went back, or never left.
                'void' => (bool) $this->reject($subject, 'voided'),
                default => $this->unexpected($subject, 'settled_at_api'),
            };
        }

        if ($lookup->status === 404 && $lookup->code === 'wager_not_found') {
            $tombstone = $this->client->settle((string) $subject->wager_reference, [
                'product' => $product,
                'version' => 1,
                'outcome' => 'void',
                'payout' => '0.00',
                'reason' => 'Not placed: no answer when it was sent',
            ]);

            if ($tombstone->ok()) {
                $this->reject($subject, 'not_placed');

                return true;
            }

            return $this->unexpected($subject, $tombstone->code ?? 'tombstone_failed');
        }

        return false;
    }

    /**
     * The player-facing [kind, message] for an API refusal code.
     *
     * @return array{0: string, 1: string}
     */
    public static function refusal(string $code): array
    {
        return match ($code) {
            'insufficient_funds' => [self::REFUSED_FUNDS, __('Your balance is too low for this stake. Deposit to place it.')],
            'player_excluded' => [self::REFUSED_NOT_ALLOWED, __('Betting is paused while your break from play runs.')],
            'player_inactive', 'player_suspended' => [self::REFUSED_NOT_ALLOWED, __('Your account cannot place bets. Please contact support.')],
            'not_linked' => [self::REFUSED_NOT_ALLOWED, __('Your account is still being set up. Try again in a minute.')],
            'stake_below_limit', 'stake_above_limit', 'payout_above_limit' => [self::REFUSED_STAKE, __('This stake is outside the limits for sports bets.')],
            'product_not_active', 'product_not_allowed', 'not_configured' => [self::REFUSED_WALLET, __('Sports betting is closed right now. Please try again later.')],
            default => [self::REFUSED_WALLET, __('Your wallet could not take this bet right now. Nothing was charged. Please try again.')],
        };
    }

    /**
     * @return array<string, string>
     */
    private function body(Bet|JackpotEntry $subject): array
    {
        $freeBet = $subject instanceof Bet && $subject->isFreeBet();

        return array_filter([
            'product' => WagerOps::product($subject),
            'reference' => (string) $subject->wager_reference,
            'funding' => $freeBet ? 'free_bet' : 'cash',
            'stake' => $freeBet ? null : Money::decimal($subject->stake),
            'free_bet_amount' => $freeBet ? Money::decimal($subject->stake) : null,
            'channel' => 'web',
        ], fn (?string $value): bool => $value !== null);
    }

    private function open(Bet|JackpotEntry $subject): Bet|JackpotEntry
    {
        $opened = DB::transaction(function () use ($subject): Bet|JackpotEntry {
            $locked = $subject::query()->whereKey($subject->id)->lockForUpdate()->firstOrFail();

            if ($this->isPending($locked)) {
                $locked->update(['status' => $locked instanceof Bet ? BetStatus::Open : JackpotEntryStatus::Open]);
            }

            return $locked;
        });

        // Results may have come in while it was being checked: settle what is already decided.
        if ($opened instanceof Bet && $opened->status === BetStatus::Open) {
            return $this->wallet->settleBet($opened);
        }

        if ($opened instanceof JackpotEntry && $opened->status === JackpotEntryStatus::Open) {
            $this->wallet->settleJackpot($opened->jackpot);
        }

        return $opened->fresh() ?? $opened;
    }

    private function reject(Bet|JackpotEntry $subject, string $code): Bet|JackpotEntry
    {
        $rejected = $this->markRejected($subject, $code);

        // The jackpot may have been waiting for this entry: with it out of the way, it can settle now.
        if ($rejected instanceof JackpotEntry && $rejected->status === JackpotEntryStatus::Rejected) {
            $this->wallet->settleJackpot($rejected->jackpot);
        }

        return $rejected;
    }

    private function markRejected(Bet|JackpotEntry $subject, string $code): Bet|JackpotEntry
    {
        return DB::transaction(function () use ($subject, $code): Bet|JackpotEntry {
            $locked = $subject::query()->whereKey($subject->id)->lockForUpdate()->firstOrFail();

            if (! $this->isPending($locked)) {
                return $locked;
            }

            // The slip key is released, so the player can send the same slip again once they fixed the cause.
            $locked->update([
                'status' => $locked instanceof Bet ? BetStatus::Rejected : JackpotEntryStatus::Rejected,
                'reject_reason' => mb_substr($code, 0, 40),
                'client_key' => null,
                'settled_at' => now('UTC'),
            ]);

            if ($locked instanceof Bet && $locked->free_bet_id !== null) {
                FreeBet::query()->whereKey($locked->free_bet_id)->where('status', FreeBetStatus::Used)
                    ->update(['status' => FreeBetStatus::Available, 'used_at' => null]);
                $locked->update(['free_bet_id' => null]);
            }

            return $locked;
        });
    }

    /**
     * Whether an earlier lookup found something only an operator can sort out, so the sweep leaves it alone for a
     * while instead of asking the API every minute.
     */
    public static function onHold(string $subjectType, int $id): bool
    {
        return Cache::has(self::holdKey($subjectType, $id));
    }

    private static function holdKey(string $subjectType, int $id): string
    {
        return "sportsbook.reconcile-hold.{$subjectType}.{$id}";
    }

    private function unexpected(Bet|JackpotEntry $subject, string $code): bool
    {
        Cache::put(self::holdKey(WagerOps::subjectType($subject), $subject->id), $code, now()->addMinutes(self::HOLD_MINUTES));

        Log::error('Sportsbook wagers: a pending placement needs an operator', [
            'subject' => WagerOps::subjectType($subject),
            'id' => $subject->id,
            'code' => $code,
        ]);

        return false;
    }

    private function isPending(Bet|JackpotEntry $subject): bool
    {
        return $subject->status === BetStatus::Pending || $subject->status === JackpotEntryStatus::Pending;
    }

    private function isOpen(Bet|JackpotEntry $subject): bool
    {
        return ! $this->isPending($subject);
    }
}
