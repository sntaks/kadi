<?php

use App\Models\User;
use App\Predict\Data\Market;
use App\Predict\Data\PredictBet;
use App\Predict\Data\Quote;
use App\Predict\PredictApi;
use App\Predict\Refusal;
use App\Predict\RefusalMessage;
use App\Support\WalletBalanceWriter;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/**
 * The API's own answers (tests/Fixtures/predict) read through the client and the data objects.
 */
function predictFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/predict/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}

function fakePredict(string $name, int $status = 200): PredictApi
{
    $fixture = predictFixture($name);
    $recorded = array_key_exists('body', $fixture);

    config(['services.kadi_api.url' => 'https://api.kadi.example/api/v1/', 'services.kadi_api.key' => 'site-key']);
    // A fresh client each time: the first fake registered would otherwise answer every later call in the test.
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response($recorded ? $fixture['body'] : $fixture, $recorded ? $fixture['status'] : $status)]);

    return app(PredictApi::class);
}

test('the three kinds of market read as documented', function (string $fixture, string $source, string $engine, bool $usesQuote, bool $reference) {
    $market = Market::fromArray(fakePredict($fixture)->market(1)->data);

    expect($market)->not->toBeNull()
        ->and($market->source)->toBe($source)
        ->and($market->engine)->toBe($engine)
        ->and($market->usesQuote())->toBe($usesQuote)
        ->and($market->odds->isReference)->toBe($reference)
        ->and($market->odds->attribution)->toBe($reference ? 'Reference prices from Polymarket' : null)
        ->and($market->odds->multiplier('yes'))->toBeGreaterThan(1)
        ->and($market->timezone)->toBe('Africa/Nairobi')
        ->and($market->bettingAvailable)->toBeTrue()
        ->and($market->bettingClosesAt)->not->toBeNull()
        ->and($market->takesBets($market->bettingClosesAt->subSecond()))->toBeTrue()
        ->and($market->takesBets($market->bettingClosesAt))->toBeFalse();
})->with([
    'pool' => ['market-pool', 'custom', 'pool', false, false],
    'polymarket' => ['market-polymarket', 'polymarket', 'reference', true, true],
    'lmsr' => ['market-lmsr', 'custom', 'lmsr', true, false],
]);

test('the Polymarket market carries its event, its link and a cut-off before the close', function () {
    $market = Market::fromArray(fakePredict('market-polymarket')->market(31)->data);

    expect($market->id)->toBe(2)
        ->and($market->eventTitle)->toBe('Worlds 2026 Winning Region')
        ->and($market->externalUrl)->toBe('https://polymarket.com/event/lol-worlds-2026-winning-region-226/will-a-team-from-lck-south-korea-win-lol-worlds-2026-115')
        ->and($market->imageUrl)->toStartWith('https://polymarket-upload.s3.us-east-2.amazonaws.com/')
        ->and($market->category)->toBe('sport')
        ->and($market->minStake)->toBe(10.0)
        ->and($market->maxStake)->toBe(5000.0)
        ->and($market->odds->multiplier('yes'))->toBe(1.52)
        ->and($market->odds->multiplier('no'))->toBe(2.53)
        ->and($market->bettingClosesAt->lessThan($market->closeAt))->toBeTrue()
        ->and($market->bettingClosesAt->utc()->toIso8601String())->toBe('2027-01-01T01:44:00+00:00');
});

test('the list and the feed give markets, paging and the feed\'s cursor fields', function () {
    $list = fakePredict('markets-list')->markets();
    $updates = fakePredict('updates')->updates('2026-11-10T09:30:00+00:00');

    expect(Market::fromArray($list->data['items'][0])->id)->toBe(12)
        ->and($list->data['pagination'])->toHaveKeys(['page', 'per_page', 'total', 'last_page'])
        ->and($updates->data['server_time'])->toBe('2026-11-10T09:30:10+00:00')
        ->and($updates->data['has_more'])->toBeFalse()
        ->and($updates->data['reference_prices_at'])->toBe('2026-11-10T09:30:08+00:00')
        ->and(Market::fromArray($updates->data['markets'][0]))->not->toBeNull();
});

test('a quiet feed answers no markets while reference prices stay fresh', function () {
    $updates = fakePredict('updates-quiet')->updates('2026-11-13T12:23:32+03:00');

    expect($updates->ok())->toBeTrue()
        ->and($updates->data['markets'])->toBe([])
        ->and($updates->data['has_more'])->toBeFalse()
        ->and($updates->data['reference_prices_at'])->toBe($updates->data['server_time']);
});

test('the live Polymarket list reads every market, and markets of one event share its title', function () {
    $list = fakePredict('markets-list-polymarket')->markets(['source' => 'polymarket']);
    $markets = collect($list->data['items'])->map(Market::fromArray(...));

    expect($markets)->toHaveCount(4)
        ->and($markets->contains(null))->toBeFalse()
        ->and($markets->every(fn (Market $market): bool => $market->isImported() && $market->usesQuote() && $market->externalUrl !== null))->toBeTrue()
        ->and($markets->where('eventTitle', 'Worlds 2026 Winning Region'))->toHaveCount(2)
        ->and($markets->firstWhere('id', 136)->eventTitle)->toBe('Pro Football: 2027 NFC Champion')
        ->and($list->data['pagination']['total'])->toBe(78);
});

test('the live history and estimate read as documented', function () {
    $history = fakePredict('history-polymarket')->history(2);
    $estimate = fakePredict('estimate-polymarket')->estimate(2, 'yes', 100);

    expect($history->data['points'][0])->toHaveKeys(['at', 'yes_probability', 'no_probability'])
        ->and($estimate->data['estimated_multiplier'])->toBe(1.52)
        ->and($estimate->data['estimated_payout'])->toBe(152)
        ->and($estimate->data['is_estimate'])->toBeTrue();
});

test('a quote reads its fixed figures', function () {
    $response = fakePredict('quote', 201)->quote(User::factory()->create(['linked_id' => 4821]), 31, 'yes', 100);
    $quote = Quote::fromArray($response->data['quote']);

    expect($quote->id)->toBe('9d2c3f10-7a41-4c8e-b0a3-52e6d1f0a7aa')
        ->and($quote->multiplier)->toBe(1.58)
        ->and($quote->potentialPayout)->toBe(158.0)
        ->and($quote->matches(31, 'yes', 100.0))->toBeTrue()
        ->and($quote->secondsRemaining(Date::parse('2026-11-12T06:00:00+00:00')))->toBe(10);
});

test('a placed bet reads as a bet, and its balance block suits the version-guarded writer', function () {
    $user = User::factory()->create(['linked_id' => 4821]);
    $response = fakePredict('bet-placed', 201)->placeBet($user, 12, 'yes', 100, 'b7c1e0d2-0000-4000-8000-000000000001');
    $bet = PredictBet::fromArray($response->data['bet']);

    expect($response->replayed())->toBeFalse()
        ->and($bet->isOpen())->toBeTrue()
        ->and($bet->oddsLabel())->toBe('Estimated')
        ->and($bet->potentialPayout)->toBe(176.0)
        ->and(app(WalletBalanceWriter::class)->writeFromResponse($user, $response->data['balance']))->toBeTrue()
        ->and((float) Cache::get("wallet_balance_{$user->id}"))->toBe(900.0);
});

test('my bets reads its items and paging', function () {
    $response = fakePredict('bets-list')->bets(User::factory()->create(['linked_id' => 4821]), 'open');

    expect(PredictBet::fromArray($response->data['items'][0])->question)->toBe('Will Gor Mahia win the 2026/27 league?')
        ->and($response->data['pagination']['total'])->toBe(1);
});

test('price changed carries the new multiplier and a fresh quote', function () {
    $response = fakePredict('refusal-price-changed', 409)->placeBet(User::factory()->create(['linked_id' => 4821]), 31, 'yes', 100, 'ref', 'old-quote');

    expect($response->refused())->toBeTrue()
        ->and($response->is('price_changed'))->toBeTrue()
        ->and($response->data['multiplier'])->toBe(1.53)
        ->and(Quote::fromArray($response->data['quote'])->id)->toBe('5b7e0c2e-1d44-4f0a-9a57-0c6f2d9b8e11')
        ->and(RefusalMessage::for($response)->action)->toBe(Refusal::NEW_QUOTE);
});

test('a full pool side offers the stake that fits', function () {
    $response = fakePredict('refusal-pool-side-full', 422)->placeBet(User::factory()->create(['linked_id' => 4821]), 12, 'yes', 5000, 'ref');
    $refusal = RefusalMessage::for($response);

    expect($refusal->action)->toBe(Refusal::USE_STAKE)
        ->and($refusal->amount)->toBe((float) predictFixture('refusal-pool-side-full')['max_stake']);
});

test('live refusals: an old cursor has no code, a bad filter and a missing market do', function () {
    $tooOld = fakePredict('refusal-since-too-old')->updates('2026-11-11T12:00:00+03:00');
    $missing = fakePredict('refusal-market-not-found')->market(99999999);
    $invalid = fakePredict('refusal-validation')->markets(['sort' => 'nope']);

    expect($tooOld->status)->toBe(422)
        ->and($tooOld->code)->toBe('http_422')
        ->and($tooOld->refused())->toBeTrue()
        ->and($missing->is('market_not_found'))->toBeTrue()
        ->and(RefusalMessage::for($missing)->action)->toBe(Refusal::CLOSED)
        ->and($invalid->is('validation_failed'))->toBeTrue()
        ->and($invalid->message)->toBe('The selected sort is invalid.');
});
