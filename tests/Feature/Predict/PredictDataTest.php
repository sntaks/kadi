<?php

use App\Predict\Data\Market;
use App\Predict\Data\PredictBet;
use App\Predict\Data\Quote;
use App\Predict\Format;
use Illuminate\Support\Facades\Date;

function polymarketMarket(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 15,
        'source' => 'polymarket',
        'engine' => 'reference',
        'question' => 'Will Arsenal beat Chelsea?',
        'description' => 'Premier League, matchday 12.',
        'resolution_criteria' => 'Resolves to Arsenal if Arsenal win in 90 minutes.',
        'resolution_source' => 'Polymarket',
        'category' => 'sport',
        'image_url' => 'https://polymarket-upload.s3.us-east-2.amazonaws.com/arsenal.png',
        'external_url' => 'https://polymarket.com/event/epl-ars-che/will-arsenal-win',
        'timezone' => 'Africa/Nairobi',
        'currency' => 'KES',
        'status' => 'open',
        'close_at' => '2026-11-14T19:00:00+00:00',
        'betting_closes_at' => '2026-11-14T15:00:00+00:00',
        'betting_available' => true,
        'resolution_deadline_at' => '2026-11-15T19:00:00+00:00',
        'result' => null,
        'min_stake' => 10,
        'max_stake' => 5000,
        'max_payout' => 100000,
        'event' => ['id' => 3, 'title' => 'Arsenal vs Chelsea'],
        'odds' => [
            'yes_label' => 'Arsenal',
            'no_label' => 'Chelsea',
            'yes_probability' => 0.415,
            'no_probability' => 0.585,
            'yes_multiplier' => 2.38,
            'no_multiplier' => null,
            'is_estimate' => true,
            'is_reference' => true,
            'attribution' => 'Reference prices from Polymarket',
            'updated_at' => '2026-11-13T09:59:58+00:00',
        ],
    ], $overrides);
}

test('a Polymarket market reads its labels, attribution, event and cut-off', function () {
    $market = Market::fromArray(polymarketMarket());

    expect($market->id)->toBe(15)
        ->and($market->isImported())->toBeTrue()
        ->and($market->usesQuote())->toBeTrue()
        ->and($market->eventTitle)->toBe('Arsenal vs Chelsea')
        ->and($market->externalUrl)->toBe('https://polymarket.com/event/epl-ars-che/will-arsenal-win')
        ->and($market->bettingClosesAt->toIso8601String())->toBe('2026-11-14T15:00:00+00:00')
        ->and($market->minStake)->toBe(10.0)
        ->and($market->odds->label('yes'))->toBe('Arsenal')
        ->and($market->odds->label('no'))->toBe('Chelsea')
        ->and($market->odds->multiplier('yes'))->toBe(2.38)
        ->and($market->odds->multiplier('no'))->toBeNull()
        ->and($market->odds->isReference)->toBeTrue()
        ->and($market->odds->attribution)->toBe('Reference prices from Polymarket');
});

test('a market with only an id and a question still reads, closed to bets', function () {
    $market = Market::fromArray(['id' => '7', 'question' => 'Will it rain?']);

    expect($market)->not->toBeNull()
        ->and($market->source)->toBe('custom')
        ->and($market->engine)->toBe('pool')
        ->and($market->usesQuote())->toBeFalse()
        ->and($market->bettingAvailable)->toBeFalse()
        ->and($market->odds)->toBeNull()
        ->and($market->eventTitle)->toBeNull()
        ->and($market->takesBets(Date::now()))->toBeFalse();
});

test('a market without an id or a question is dropped', function (array $market) {
    expect(Market::fromArray($market))->toBeNull();
})->with([
    'no id' => [['question' => 'Will it rain?']],
    'no question' => [['id' => 4, 'question' => '  ']],
]);

test('only a polymarket.com https link is kept, and only https images', function (?string $url, ?string $expected) {
    $market = Market::fromArray(polymarketMarket(['external_url' => $url, 'image_url' => $url]));

    expect($market->externalUrl)->toBe($expected);
})->with([
    'polymarket' => ['https://polymarket.com/event/a/b', 'https://polymarket.com/event/a/b'],
    'www' => ['https://www.polymarket.com/event/a/b', 'https://www.polymarket.com/event/a/b'],
    'http' => ['http://polymarket.com/event/a/b', null],
    'look-alike host' => ['https://polymarket.com.evil.example/event/a/b', null],
    'other site' => ['https://example.com/polymarket.com', null],
    'script' => ['javascript:alert(1)', null],
    'missing' => [null, null],
]);

test('an image must be https', function () {
    expect(Market::fromArray(polymarketMarket(['image_url' => 'http://cdn.example/a.png']))->imageUrl)->toBeNull()
        ->and(Market::fromArray(polymarketMarket(['image_url' => 'https://cdn.example/a.png']))->imageUrl)->toBe('https://cdn.example/a.png');
});

test('attribution falls back to our text on a reference market and is absent elsewhere', function () {
    $reference = Market::fromArray(polymarketMarket(['odds' => ['attribution' => null]]));
    $pool = Market::fromArray(polymarketMarket(['source' => 'custom', 'engine' => 'pool', 'odds' => ['is_reference' => false]]));

    expect($reference->odds->attribution)->toBe('Reference prices from Polymarket')
        ->and($pool->odds->attribution)->toBeNull();
});

test('betting stops at the cut-off by our own clock, though the API still says available', function () {
    $market = Market::fromArray(polymarketMarket());

    expect($market->takesBets(Date::parse('2026-11-14T14:59:59+00:00')))->toBeTrue()
        ->and($market->sideAvailable('yes', Date::parse('2026-11-14T14:59:59+00:00')))->toBeTrue()
        ->and($market->sideAvailable('no', Date::parse('2026-11-14T14:59:59+00:00')))->toBeFalse()
        ->and($market->takesBets(Date::parse('2026-11-14T15:00:00+00:00')))->toBeFalse()
        ->and($market->sideAvailable('yes', Date::parse('2026-11-14T18:00:00+03:00')))->toBeFalse();
});

test('a suspended or unavailable market takes no bets', function (array $overrides) {
    expect(Market::fromArray(polymarketMarket($overrides))->takesBets(Date::parse('2026-11-13T10:00:00+00:00')))->toBeFalse();
})->with([
    'suspended' => [['status' => 'suspended']],
    'closed' => [['status' => 'closed']],
    'not available' => [['betting_available' => false]],
]);

test('a multiplier of 1.00 or less is not on offer', function () {
    $odds = Market::fromArray(polymarketMarket(['odds' => ['yes_multiplier' => 1, 'no_multiplier' => '1.01']]))->odds;

    expect($odds->multiplier('yes'))->toBeNull()->and($odds->multiplier('no'))->toBe(1.01);
});

test('a quote counts down on our clock and never keeps the price', function () {
    $quote = Quote::fromArray([
        'quote_id' => '0b6f1c7e-1111-4222-8333-444455556666', 'market_id' => 15, 'side' => 'yes', 'stake' => 100,
        'multiplier' => 1.58, 'potential_payout' => 158, 'price' => 0.6329, 'expires_at' => '2026-11-13T13:00:10+03:00',
    ]);

    expect($quote->id)->toBe('0b6f1c7e-1111-4222-8333-444455556666')
        ->and($quote->multiplier)->toBe(1.58)
        ->and($quote->potentialPayout)->toBe(158.0)
        ->and(property_exists($quote, 'price'))->toBeFalse()
        ->and($quote->secondsRemaining(Date::parse('2026-11-13T10:00:00+00:00')))->toBe(10)
        ->and($quote->secondsRemaining(Date::parse('2026-11-13T10:00:03.400+00:00')))->toBe(6)
        ->and($quote->expired(Date::parse('2026-11-13T10:00:09+00:00')))->toBeFalse()
        ->and($quote->expired(Date::parse('2026-11-13T10:00:10+00:00')))->toBeTrue()
        ->and($quote->secondsRemaining(Date::parse('2026-11-13T10:05:00+00:00')))->toBe(0)
        ->and($quote->matches(15, 'yes', 100.0))->toBeTrue()
        ->and($quote->matches(15, 'no', 100.0))->toBeFalse()
        ->and($quote->matches(15, 'yes', 100.5))->toBeFalse();
});

test('an incomplete quote is not a quote', function (array $without) {
    $quote = array_diff_key([
        'quote_id' => 'q', 'market_id' => 15, 'side' => 'yes', 'stake' => 100,
        'multiplier' => 1.58, 'potential_payout' => 158, 'expires_at' => '2026-11-13T13:00:10+03:00',
    ], array_flip($without));

    expect(Quote::fromArray($quote))->toBeNull();
})->with([[['quote_id']], [['expires_at']], [['multiplier']], [['potential_payout']], [['side']]]);

test('an open bet is labelled fixed or estimated, a settled one is neither', function () {
    $bet = fn (array $overrides) => PredictBet::fromArray($overrides + [
        'id' => 1, 'market_id' => 15, 'question' => 'Will Arsenal beat Chelsea?', 'side' => 'yes', 'stake' => 100,
        'status' => 'open', 'multiplier' => 1.58, 'potential_payout' => 158, 'payout' => null, 'is_estimate' => false,
        'placed_at' => '2026-11-13T10:00:00+00:00', 'settled_at' => null, 'reference' => 'never-kept',
    ]);

    expect($bet([])->oddsLabel())->toBe('Fixed odds')
        ->and($bet(['is_estimate' => true])->oddsLabel())->toBe('Estimated')
        ->and($bet(['status' => 'won', 'payout' => 158])->oddsLabel())->toBeNull()
        ->and($bet(['status' => 'won', 'payout' => 158])->payout)->toBe(158.0)
        ->and($bet(['status' => 'void'])->isVoid())->toBeTrue()
        ->and(property_exists($bet([]), 'reference'))->toBeFalse()
        ->and(PredictBet::fromArray(['id' => 1]))->toBeNull();
});

test('figures are written one way everywhere', function () {
    expect(Format::kes(158))->toBe('KES 158.00')
        ->and(Format::kes('1234.5'))->toBe('KES 1,234.50')
        ->and(Format::multiplier(2.375))->toBe('2.38x')
        ->and(Format::multiplier(2))->toBe('2.00x')
        ->and(Format::multiplier(null))->toBeNull()
        ->and(Format::percent(0.415))->toBe('42%')
        ->and(Format::percent(0.004))->toBe('0%')
        ->and(Format::percent(1.2))->toBe('100%')
        ->and(Format::percent(null))->toBeNull()
        ->and(Format::time('2026-11-13T13:00:10+03:00')->utc()->toIso8601String())->toBe('2026-11-13T10:00:10+00:00')
        ->and(Format::time('not a time'))->toBeNull()
        ->and(Format::time(null))->toBeNull();
});
