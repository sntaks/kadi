<?php

use App\Models\User;
use App\Sportsbook\Betting\PlaceBet;
use App\Sportsbook\Betting\Settlement\RecordResult;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeWagerApi;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Loads config/kadi.php as if the given environment variables were set, whatever the developer's own
 * .env says. env() reads $_ENV, $_SERVER and putenv, so all three are overridden and then restored.
 *
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function kadiConfigWithEnv(array $env): array
{
    $saved = [];

    foreach ($env as $name => $value) {
        $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    try {
        return require config_path('kadi.php');
    } finally {
        foreach ($saved as $name => [$fromEnv, $fromServer]) {
            putenv($name);
            $fromEnv === null ? Arr::forget($_ENV, $name) : $_ENV[$name] = $fromEnv;
            $fromServer === null ? Arr::forget($_SERVER, $name) : $_SERVER[$name] = $fromServer;
        }
    }
}

/*
|--------------------------------------------------------------------------
| The merged sportsbook (ported from its own tests/Pest.php)
|--------------------------------------------------------------------------
*/

/**
 * Decode a recorded Odds API response from tests/Fixtures/sportsbook/odds-api.
 *
 * @return list<array<string, mixed>>
 */
function oddsApiFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/sportsbook/odds-api/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Use the live Odds API driver against a fake host. Any request without a matching Http::fake() fails the test,
 * so no test can ever reach the real API.
 */
function useLiveOddsApi(): void
{
    config([
        'sportsbook.odds_api.driver' => 'live',
        'sportsbook.odds_api.region' => 'uk',
        'services.odds_api.key' => 'test-api-key',
        'services.odds_api.base_url' => 'https://odds-api.test/v4',
    ]);

    Http::preventStrayRequests();
}

/**
 * An upcoming fixture in a synced competition with an open 1X2 market priced at the given prices.
 *
 * @param  array<string, mixed>  $fixtureAttributes
 * @param  array<string, string>  $prices  Outcome => price.
 */
function pricedFixture(array $fixtureAttributes = [], array $prices = ['home' => '2.10', 'draw' => '3.40', 'away' => '3.50']): Fixture
{
    $fixture = Fixture::factory()
        ->for(Competition::factory()->synced())
        ->create($fixtureAttributes);
    $market = Market::factory()->for($fixture)->create();
    $names = ['home' => $fixture->home_team, 'draw' => 'Draw', 'away' => $fixture->away_team];

    foreach ($prices as $outcome => $price) {
        Selection::factory()->for($market)->create(['outcome' => $outcome, 'name' => $names[$outcome] ?? $outcome, 'feed_price' => $price]);
    }

    return $fixture->load('matchResultMarket.selections');
}

/**
 * A bet slip pick as the browser sends it, at the price the player saw (the current price by default).
 *
 * @return array{fixture_id: int, selection_id: int, price: string|null}
 */
function slipPick(Selection $selection, ?string $price = null): array
{
    return ['fixture_id' => $selection->market->fixture_id, 'selection_id' => $selection->id, 'price' => $price ?? $selection->price()];
}

/** The fixture's 1X2 selection for an outcome. */
function selectionFor(Fixture $fixture, string $outcome = 'home'): Selection
{
    return $fixture->matchResultMarket->selections->firstWhere('outcome', $outcome);
}

/**
 * An open KES 99 jackpot on three upcoming matches: KES 10,000 shared by entries with all three right, KES 1,000
 * by entries with two. One void match is allowed.
 */
function openJackpot(int $matches = 3): Jackpot
{
    return Jackpot::factory()->open()->withMatches($matches)->create()->load('jackpotFixtures.fixture');
}

/**
 * A stateful Kadi wager API behind Http::fake, with sports betting switched on (tests/Support/FakeWagerApi.php).
 */
function wagerApi(): FakeWagerApi
{
    return FakeWagerApi::install();
}

/**
 * Place a bet on the given fixtures' 1X2 outcomes, e.g. [[$fixture, 'home'], [$other, 'draw']].
 *
 * @param  list<array{0: Fixture, 1: string}>  $picks
 */
function betOn(User $player, array $picks, string $stake = '100', ?int $freeBetId = null): Bet
{
    return app(PlaceBet::class)->handle($player, array_map(fn (array $pick): array => slipPick(selectionFor($pick[0], $pick[1])), $picks), $stake, freeBetId: $freeBetId);
}

/**
 * Record a fixture's full-time score from the feed (or as given).
 *
 * @param  array<string, mixed>  $extra
 * @return array{legs: int, bets: int}
 */
function recordScore(Fixture $fixture, int $home, int $away, array $extra = [], string $source = FixtureResult::SOURCE_FEED, ?User $admin = null): array
{
    return app(RecordResult::class)->handle($fixture, ['home_score' => $home, 'away_score' => $away, ...$extra], $source, $admin);
}

/**
 * Reads an `account_id` the way a game server does (encrypt_account_id()): checks the HMAC with the configured MAC key,
 * then decrypts. Returns null when the format or the tag is wrong.
 */
function decryptAccountId(string $token): ?string
{
    if (! str_starts_with($token, 'v1.')) {
        return null;
    }

    $raw = base64_decode(strtr(substr($token, 3), '-_', '+/'), true);
    if ($raw === false || strlen($raw) < 16 + 16 + 32) {
        return null;
    }

    [$body, $tag] = [substr($raw, 0, -32), substr($raw, -32)];
    $expected = hash_hmac('sha256', 'account-id:v1:'.$body, hex2bin(config('games.launch.account_id_mac_key')), true);
    if (! hash_equals($expected, $tag)) {
        return null;
    }

    $plain = openssl_decrypt(substr($body, 16), 'aes-256-cbc', hex2bin(config('games.launch.account_id_key')), OPENSSL_RAW_DATA, substr($body, 0, 16));

    return $plain === false ? null : $plain;
}

/**
 * A Kadi API prediction answer from tests/Fixtures/predict: live ones are `{status, body}`, documentation samples the
 * whole body.
 *
 * @return array<string, mixed>
 */
function predictFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/predict/{$name}.json")), true, flags: JSON_THROW_ON_ERROR);
}
