<?php

use App\Models\User;
use App\Predict\PredictApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;

const PREDICT_BASE = 'https://api.kadi.example/api/v1/';
const PREDICT_SITE_KEY = 'site-key-for-tests';

beforeEach(function () {
    config(['services.kadi_api.url' => PREDICT_BASE, 'services.kadi_api.key' => PREDICT_SITE_KEY]);
    Http::preventStrayRequests();
});

function predictPlayer(): User
{
    return User::factory()->create(['linked_id' => 4821]);
}

test('reads send the site key and the documented queries', function () {
    Http::fake([PREDICT_BASE.'predict/*' => Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => []]])]);

    $api = app(PredictApi::class);

    expect($api->markets(['source' => 'polymarket', 'category' => null, 'sort' => 'closing_soon', 'page' => 2])->ok())->toBeTrue();
    $api->market(15);
    $api->history(15);
    $api->estimate(15, 'yes', 100);
    $api->updates('2026-11-13T10:00:00+03:00');
    $api->updates('2026-11-13T10:00:00+03:00', 900);

    $sent = Http::recorded()->map(fn (array $pair): Request => $pair[0]);

    expect($sent->map(fn (Request $request): string => urldecode($request->url()))->all())->toBe([
        PREDICT_BASE.'predict/markets?source=polymarket&sort=closing_soon&page=2',
        PREDICT_BASE.'predict/markets/15',
        PREDICT_BASE.'predict/markets/15/history',
        PREDICT_BASE.'predict/markets/15/estimate?side=yes&stake=100.00',
        PREDICT_BASE.'predict/updates?since=2026-11-13T10:00:00+03:00',
        PREDICT_BASE.'predict/updates?since=2026-11-13T10:00:00+03:00&after_id=900',
    ]);

    $sent->each(function (Request $request) {
        expect($request->header('x-api-key'))->toBe([PREDICT_SITE_KEY])
            ->and($request->method())->toBe('GET')
            ->and($request->hasHeader('Idempotency-Key'))->toBeFalse();
    });
});

test('a quote and a bet go to the signed-in player\'s own customer, without an Idempotency-Key', function () {
    Http::fake([PREDICT_BASE.'customers/*' => Http::response(['success' => true, 'data' => ['replayed' => false]], 201)]);

    $user = predictPlayer();
    $api = app(PredictApi::class);

    $api->quote($user, 15, 'yes', 100);
    $api->placeBet($user, 15, 'yes', 100, 'b7c1e0d2-0000-4000-8000-000000000001', 'quote-uuid');
    $api->placeBet($user, 16, 'no', 50.5, 'b7c1e0d2-0000-4000-8000-000000000002');
    $api->bets($user, 'settled', 2);

    $sent = Http::recorded()->map(fn (array $pair): Request => $pair[0])->values();

    $sent->each(function (Request $request) {
        preg_match('#customers/([^/]+)/predict/#', $request->url(), $match);

        expect(decryptOpenSSL(urldecode($match[1])))->toBe('4821')
            ->and($request->hasHeader('Idempotency-Key'))->toBeFalse()
            ->and($request->header('x-api-key'))->toBe([PREDICT_SITE_KEY]);
    });

    expect($sent[0]->method())->toBe('POST')
        ->and($sent[0]->url())->toEndWith('/predict/quotes')
        ->and($sent[0]->data())->toBe(['market_id' => 15, 'side' => 'yes', 'stake' => '100.00'])
        ->and($sent[1]->url())->toEndWith('/predict/bets')
        ->and($sent[1]->data())->toBe([
            'market_id' => 15, 'side' => 'yes', 'stake' => '100.00',
            'reference' => 'b7c1e0d2-0000-4000-8000-000000000001', 'quote_id' => 'quote-uuid',
        ])
        ->and($sent[2]->data())->toBe([
            'market_id' => 16, 'side' => 'no', 'stake' => '50.50', 'reference' => 'b7c1e0d2-0000-4000-8000-000000000002',
        ])
        ->and($sent[3]->method())->toBe('GET')
        ->and(urldecode($sent[3]->url()))->toEndWith('/predict/bets?status=settled&page=2&per_page=20');
});

test('a player who is not linked yet never reaches the API', function () {
    Http::fake();

    $response = app(PredictApi::class)->quote(User::factory()->create(['linked_id' => null]), 15, 'yes', 100);

    expect($response->refused())->toBeTrue()->and($response->code)->toBe('customer_not_found');
    Http::assertNothingSent();
});

test('a success carries the data block and the replay flag', function () {
    Http::fake([PREDICT_BASE.'customers/*' => Http::response([
        'success' => true,
        'data' => ['bet' => ['id' => 9], 'balance' => null, 'replayed' => true],
    ])]);

    $response = app(PredictApi::class)->placeBet(predictPlayer(), 15, 'yes', 100, 'ref');

    expect($response->ok())->toBeTrue()
        ->and($response->replayed())->toBeTrue()
        ->and($response->unknown())->toBeFalse()
        ->and($response->data['bet'])->toBe(['id' => 9]);
});

test('a refusal keeps its code, message and extra fields', function () {
    Http::fake([PREDICT_BASE.'customers/*' => Http::response([
        'success' => false, 'message' => 'This stake is above what the player may still stake on this market',
        'code' => 'customer_limit', 'retryable' => false, 'remaining' => 250,
    ], 422)]);

    $response = app(PredictApi::class)->placeBet(predictPlayer(), 15, 'yes', 1000, 'ref', 'quote');

    expect($response->refused())->toBeTrue()
        ->and($response->unknown())->toBeFalse()
        ->and($response->code)->toBe('customer_limit')
        ->and($response->message)->toBe('This stake is above what the player may still stake on this market')
        ->and($response->data)->toBe(['remaining' => 250]);
});

test('a 4xx without a code is a definite refusal', function () {
    Http::fake([PREDICT_BASE.'customers/*' => Http::response(['message' => 'The stake field must be a number.', 'errors' => ['stake' => ['x']]], 422)]);

    $response = app(PredictApi::class)->quote(predictPlayer(), 15, 'yes', 100);

    expect($response->refused())->toBeTrue()
        ->and($response->code)->toBe('http_422')
        ->and($response->message)->toBe('The stake field must be a number.');
});

test('a 429 is an unknown outcome read by status, with Retry-After', function (array $body) {
    Http::fake([PREDICT_BASE.'customers/*' => Http::response($body, 429, ['Retry-After' => '7'])]);

    $response = app(PredictApi::class)->placeBet(predictPlayer(), 15, 'yes', 100, 'ref', 'quote');

    expect($response->unknown())->toBeTrue()
        ->and($response->refused())->toBeFalse()
        ->and($response->rateLimited())->toBeTrue()
        ->and($response->retryAfter)->toBe(7);
})->with([
    'shaped' => [['success' => false, 'message' => 'Too many requests', 'code' => 'rate_limited', 'retryable' => true]],
    'plain' => [['message' => 'Too Many Attempts.']],
]);

test('a 5xx and no answer at all are unknown outcomes, and nothing is retried', function () {
    $attempts = 0;

    Http::fake([
        PREDICT_BASE.'predict/markets/1' => Http::response('<html>Bad gateway</html>', 502),
        PREDICT_BASE.'predict/markets/2' => function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('timed out');
        },
    ]);

    $api = app(PredictApi::class);
    $down = $api->market(1);
    $silent = $api->market(2);

    expect($down->unknown())->toBeTrue()
        ->and($down->code)->toBe('http_502')
        ->and($silent->unknown())->toBeTrue()
        ->and($silent->status)->toBeNull()
        ->and($silent->code)->toBe('no_answer');

    // The 502 was sent once; the call that never connected is not recorded, so it is counted by hand.
    Http::assertSentCount(1);
    expect($attempts)->toBe(1);
});

test('without a site key nothing is sent', function () {
    config(['services.kadi_api.key' => null]);
    Http::fake();

    $response = app(PredictApi::class)->markets();

    expect($response->code)->toBe('not_configured')->and($response->ok())->toBeFalse();
    Http::assertNothingSent();
});

test('logs never carry the site key, the customer path, a reference, a quote id or a stake', function () {
    Log::spy();

    Http::fake([
        PREDICT_BASE.'customers/*' => Http::response(['success' => false, 'message' => 'Please retry', 'code' => 'server_error'], 500),
    ]);

    $user = predictPlayer();
    app(PredictApi::class)->placeBet($user, 15, 'yes', 123.45, 'the-reference', 'the-quote-id');

    Http::fake([PREDICT_BASE.'customers/*' => fn () => throw new ConnectionException('timed out')]);
    app(PredictApi::class)->placeBet($user, 15, 'yes', 123.45, 'the-reference', 'the-quote-id');

    Log::shouldHaveReceived('warning')->atLeast()->once()->withArgs(function (string $message, array $context = []) {
        $line = $message.' '.json_encode($context);

        foreach ([PREDICT_SITE_KEY, 'customers/', 'the-reference', 'the-quote-id', '123.45', '4821'] as $secret) {
            expect($line)->not->toContain($secret);
        }

        return true;
    });
});

test('nothing in the app calls the API\'s staff prediction routes', function () {
    $offenders = collect(Finder::create()->files()->in([app_path(), resource_path(), base_path('routes'), config_path()])->name(['*.php', '*.js']))
        ->filter(fn (SplFileInfo $file): bool => str_contains(strtolower($file->getContents()), 'predict/admin'))
        ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
