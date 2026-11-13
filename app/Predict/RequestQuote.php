<?php

namespace App\Predict;

use App\Models\User;
use App\Predict\Data\Market;
use App\Predict\Data\Quote;

/**
 * Asks for a quote (POST customers/{id}/predict/quotes): fixed figures for this player, side and stake, good for
 * about 10 seconds and usable once. Never retried: a new quote is the player's choice. A pool market answers
 * `quote_not_needed`, and the slip switches to placing without one.
 */
class RequestQuote
{
    public function __construct(private PredictApi $api) {}

    public function handle(User $user, Market $market, string $side, float $stake): Quote|Refusal
    {
        $response = $this->api->quote($user, $market->id, $side, $stake);

        if ($response->ok()) {
            $quote = is_array($response->data['quote'] ?? null) ? Quote::fromArray($response->data['quote']) : null;

            // A quote for anything other than what was asked is never used.
            return $quote !== null && $quote->matches($market->id, $side, $stake)
                ? $quote
                : new Refusal('quote_invalid', __('We could not get a price just now. Try again.'), Refusal::NEW_QUOTE);
        }

        if ($response->is('quote_not_needed')) {
            return new Refusal('quote_not_needed', '');
        }

        return RefusalMessage::for($response);
    }
}
