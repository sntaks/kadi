<?php

namespace App\Predict;

/**
 * From an API refusal to what the player reads. The API's own `message` is shown unless we can say something
 * more useful, and each code gets the one next step that fits it. Codes are read, never message text.
 */
final class RefusalMessage
{
    public static function for(PredictResponse $response): Refusal
    {
        $code = (string) $response->code;
        $data = $response->data;
        $said = $response->message ?? __('This bet could not be placed.');

        if ($response->rateLimited()) {
            return new Refusal($code, __('Too many requests right now. Try again in a moment.'), Refusal::RETRY, retryAfter: $response->retryAfter ?? 5);
        }

        if ($response->unknown()) {
            return new Refusal($code, __('We could not reach the betting service. Try again.'), Refusal::RETRY);
        }

        return match ($code) {
            'insufficient_funds' => new Refusal($code, __('Your balance is too low for this stake. Deposit to place it.'), Refusal::DEPOSIT),

            'quote_expired', 'quote_used', 'quote_mismatch', 'quote_required', 'price_changed' => new Refusal($code, $said, Refusal::NEW_QUOTE),

            'price_unavailable' => new Refusal($code, __('Odds unavailable right now. Try again shortly.')),

            'odds_unavailable' => new Refusal($code, __('This side has no odds on offer. You can still bet the other side.'), Refusal::OTHER_SIDE),

            'exposure_limit', 'payout_above_limit' => new Refusal($code, __('This side cannot take that stake right now. Try a smaller stake, or try again later.'), Refusal::SMALLER_STAKE),

            'customer_limit' => self::upTo(
                $code,
                Format::float($data['remaining'] ?? null),
                fn (string $amount): string => __('You can stake up to :amount more on this market.', ['amount' => $amount]),
                __('You have reached your stake limit on this market.'),
            ),

            'pool_side_full' => self::upTo(
                $code,
                Format::float($data['max_stake'] ?? null),
                fn (string $amount): string => __('This side can take up to :amount right now.', ['amount' => $amount]),
                __('This side cannot take more bets right now.'),
            ),

            'stake_above_limit' => self::upTo(
                $code,
                Format::float($data['max_stake'] ?? null),
                fn (string $amount): string => __('The most you can stake on this market is :amount.', ['amount' => $amount]),
                $said,
            ),

            'stake_below_limit' => self::upTo(
                $code,
                Format::float($data['min_stake'] ?? null),
                fn (string $amount): string => __('The least you can stake on this market is :amount.', ['amount' => $amount]),
                $said,
            ),

            'betting_closed' => new Refusal($code, __('Betting on this market has closed.'), Refusal::CLOSED, closesAt: Format::time($data['betting_closes_at'] ?? null)),

            'market_not_open', 'market_not_found' => new Refusal($code, __('This market is not taking bets.'), Refusal::CLOSED),

            'player_excluded' => new Refusal($code, __('Betting is paused while your break from play runs.')),

            'player_blocked', 'player_inactive' => new Refusal($code, __('You can\'t bet on this market.')),

            'customer_not_found' => new Refusal($code, __('Your account is still being set up. Try again in a minute.')),

            'product_not_active', 'product_not_allowed', 'not_configured' => new Refusal($code, __('Prediction bets are not available right now.'), Refusal::CLOSED),

            'reference_conflict' => new Refusal($code, __('Something went wrong with this bet. Check My bets before you try again.')),

            default => new Refusal($code, $said),
        };
    }

    /**
     * A limit refusal: offer the amount the API says would be accepted, or say there is none.
     *
     * @param  callable(string): string  $offer
     */
    private static function upTo(string $code, ?float $amount, callable $offer, string $none): Refusal
    {
        return $amount !== null && $amount > 0
            ? new Refusal($code, $offer(Format::kes($amount)), Refusal::USE_STAKE, amount: $amount)
            : new Refusal($code, $none);
    }
}
