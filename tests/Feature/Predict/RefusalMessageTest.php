<?php

use App\Predict\PredictResponse;
use App\Predict\Refusal;
use App\Predict\RefusalMessage;

test('each refusal code gets its one next step', function (string $code, int $status, array $data, ?string $action, ?float $amount) {
    $refusal = RefusalMessage::for(new PredictResponse($status, $code, 'What the API said', $data));

    expect($refusal->code)->toBe($code)
        ->and($refusal->action)->toBe($action)
        ->and($refusal->amount)->toBe($amount)
        ->and($refusal->message)->not->toBe('')
        ->and(strtolower($refusal->message))->not->toContain('guarantee');
})->with([
    ['insufficient_funds', 422, [], Refusal::DEPOSIT, null],
    ['quote_expired', 409, [], Refusal::NEW_QUOTE, null],
    ['quote_used', 409, [], Refusal::NEW_QUOTE, null],
    ['quote_mismatch', 409, [], Refusal::NEW_QUOTE, null],
    ['quote_required', 422, [], Refusal::NEW_QUOTE, null],
    ['price_changed', 409, ['multiplier' => 1.5], Refusal::NEW_QUOTE, null],
    ['price_unavailable', 409, ['reason' => 'stale'], null, null],
    ['odds_unavailable', 409, [], Refusal::OTHER_SIDE, null],
    ['exposure_limit', 409, [], Refusal::SMALLER_STAKE, null],
    ['payout_above_limit', 422, [], Refusal::SMALLER_STAKE, null],
    ['customer_limit', 422, ['remaining' => 250], Refusal::USE_STAKE, 250.0],
    ['customer_limit', 422, ['remaining' => 0], null, null],
    ['pool_side_full', 422, ['max_stake' => 400], Refusal::USE_STAKE, 400.0],
    ['pool_side_full', 422, ['max_stake' => 0], null, null],
    ['stake_above_limit', 422, ['max_stake' => 5000], Refusal::USE_STAKE, 5000.0],
    ['stake_below_limit', 422, ['min_stake' => 10], Refusal::USE_STAKE, 10.0],
    ['betting_closed', 409, ['betting_closes_at' => '2026-11-14T15:00:00+00:00'], Refusal::CLOSED, null],
    ['market_not_open', 409, ['status' => 'suspended'], Refusal::CLOSED, null],
    ['market_not_found', 404, [], Refusal::CLOSED, null],
    ['player_excluded', 403, [], null, null],
    ['player_blocked', 403, [], null, null],
    ['player_inactive', 403, [], null, null],
    ['customer_not_found', 404, [], null, null],
    ['product_not_active', 403, [], Refusal::CLOSED, null],
    ['reference_conflict', 409, [], null, null],
    ['quote_not_needed', 409, [], null, null],
]);

test('limit refusals quote the amount in KES', function () {
    $refusal = RefusalMessage::for(new PredictResponse(422, 'customer_limit', 'x', ['remaining' => 250]));

    expect($refusal->message)->toBe('You can stake up to KES 250.00 more on this market.');
});

test('betting closed carries the cut-off', function () {
    $refusal = RefusalMessage::for(new PredictResponse(409, 'betting_closed', 'x', ['betting_closes_at' => '2026-11-14T15:00:00+00:00']));

    expect($refusal->closesAt->toIso8601String())->toBe('2026-11-14T15:00:00+00:00');
});

test('an unknown code shows what the API said, and a code-less refusal still says something', function () {
    expect(RefusalMessage::for(new PredictResponse(409, 'brand_new_code', 'A new reason from the API'))->message)->toBe('A new reason from the API')
        ->and(RefusalMessage::for(new PredictResponse(422, 'http_422'))->message)->toBe('This bet could not be placed.');
});

test('a 429, a 5xx and no answer ask to try again, never by code', function () {
    $limited = RefusalMessage::for(new PredictResponse(429, 'http_429', 'Too Many Attempts.', [], 7));
    $down = RefusalMessage::for(new PredictResponse(503, 'polymarket_unavailable', 'Polymarket cannot be reached'));
    $silent = RefusalMessage::for(PredictResponse::noAnswer());

    expect($limited->action)->toBe(Refusal::RETRY)
        ->and($limited->retryAfter)->toBe(7)
        ->and($down->action)->toBe(Refusal::RETRY)
        ->and($silent->action)->toBe(Refusal::RETRY)
        ->and($silent->retryAfter)->toBeNull();
});
