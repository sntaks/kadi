<?php

namespace App\Predict;

use App\Activity\PlayerActivity;
use App\Models\User;
use App\Predict\Data\Market;
use App\Predict\Data\PredictBet;
use App\Predict\Data\Quote;
use App\Services\WalletBalanceFetcher;
use App\Support\WalletBalanceWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Places one prediction bet (POST customers/{id}/predict/bets). The API owns the money and the outcome; this class
 * makes sure an attempt can never be lost or doubled:
 *
 * - One placement per player at a time (a lock), so two tabs cannot place at once.
 * - The attempt (reference, market, side, stake, quote) is saved in PendingAttempt before the call and cleared on
 *   any definite answer. The same reference with the same market, side and stake never takes the money twice.
 * - No answer or a 5xx gets one retry in this request with the same reference and quote; a 429 gets none (no
 *   sleeping in a web request). Still unknown: the attempt stays pending for "Try again", never swallowed.
 * - The balance from the answer goes through WalletBalanceWriter (version-guarded); a replay carries none, so the
 *   wallet is read again instead.
 *
 * Never logs the reference, the quote id, the stake or the customer's identifier.
 */
class PlaceBet
{
    public function __construct(
        private PredictApi $api,
        private WalletBalanceWriter $balances,
        private WalletBalanceFetcher $fetcher,
        private PlayerActivity $activity,
    ) {}

    /**
     * @param  bool  $retrying  this is "Try again" after an unknown outcome (same reference)
     */
    public function handle(User $user, Market $market, string $side, float $stake, string $reference, ?string $quoteId = null, bool $retrying = false): Placement
    {
        $lock = Cache::lock("predict-bet:{$user->getKey()}", 30);

        if (! $lock->get()) {
            return Placement::busy();
        }

        try {
            return $this->place($user, $market, $side, $stake, $reference, $quoteId, $retrying);
        } finally {
            $lock->release();
        }
    }

    private function place(User $user, Market $market, string $side, float $stake, string $reference, ?string $quoteId, bool $retrying): Placement
    {
        PendingAttempt::put($user, [
            'reference' => $reference,
            'market_id' => $market->id,
            'side' => $side,
            'stake' => $stake,
            'quote_id' => $quoteId,
        ]);

        $response = $this->api->placeBet($user, $market->id, $side, $stake, $reference, $quoteId);

        // A timeout or a 5xx may not have reached the API, or may have: ask once more with the same reference.
        if ($response->unknown() && ! $response->rateLimited()) {
            $response = $this->api->placeBet($user, $market->id, $side, $stake, $reference, $quoteId);
        }

        if ($response->unknown()) {
            MyBetsCache::bump($user);

            return Placement::unknown($response->retryAfter);
        }

        PendingAttempt::forget($user);

        if ($response->ok()) {
            return $this->placed($user, $market, $response);
        }

        if ($response->is('price_changed')) {
            $fresh = is_array($response->data['quote'] ?? null) ? Quote::fromArray($response->data['quote']) : null;

            return Placement::changed(Format::float($response->data['multiplier'] ?? null), $fresh);
        }

        // On "Try again", KadiApi checks the reference before the quote: a quote refusal then means this bet was
        // never placed, not that something is wrong with a placed one.
        if ($retrying && in_array($response->code, ['quote_expired', 'quote_used', 'quote_mismatch'], true)) {
            return Placement::refused(new Refusal((string) $response->code, __('Your bet was not placed. Get a new price to try again.'), Refusal::NEW_QUOTE));
        }

        if ($response->is('price_unavailable')) {
            // The API's reason, and only that: never the market, the stake or the player.
            Log::info('Predictions: price unavailable on a bet', ['reason' => is_string($response->data['reason'] ?? null) ? $response->data['reason'] : null]);
        }

        return Placement::refused(RefusalMessage::for($response));
    }

    private function placed(User $user, Market $market, PredictResponse $response): Placement
    {
        $replayed = $response->replayed();
        $bet = is_array($response->data['bet'] ?? null) ? PredictBet::fromArray($response->data['bet']) : null;

        // A replay moved no money now and carries no balance; a balance without a version cannot be ordered. In
        // both cases the wallet is read again rather than guessed.
        if ($replayed || ! $this->balances->writeFromResponse($user, $response->data['balance'] ?? null)) {
            try {
                $this->fetcher->refresh($user);
            } catch (Throwable) {
                // The wallet pill catches up on its own poll.
            }
        }

        MyBetsCache::bump($user);

        if (! $replayed) {
            $this->activity->record('predict_bet_placed', $user, ['source' => $market->source, 'engine' => $market->engine]);
        }

        return Placement::placed($bet, $replayed);
    }
}
