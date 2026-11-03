<?php

use App\Livewire\Games\History;
use App\Models\GameDispute;
use App\Models\User;
use App\Services\GameDisputeService;
use App\Support\PlayedGame;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Players see their latest games, tournaments and jackpots and can report a lost game or a lost round.
 * The complaint goes to KadiApi first; only once it is accepted is it recorded and the player's pending
 * kadi.game_level_pending rows for that game set to `disputed`.
 */
function recentGamesBody(array $overrides = []): array
{
    return array_merge([
        'single_games' => [
            ['game_wallet_id' => 812, 'game_id' => 'GAME-5521', 'game_type' => 'Single Game', 'players' => 2, 'amount' => 100.0, 'state' => 'loss', 'created_at' => '2026-09-22 18:04:11'],
            ['game_wallet_id' => 813, 'game_id' => 'GAME-5522', 'game_type' => 'Single Game', 'players' => 2, 'amount' => 50.0, 'state' => 'win', 'created_at' => '2026-09-22 18:01:00'],
        ],
        'tournament_games' => [[
            'competition_wallet_id' => 301, 'competition_id' => 'TOURN-88', 'cmp_uid' => '74120', 'game_type' => 1,
            'level' => 2, 'balance' => 0.0, 'status' => 0, 'created_at' => '2026-09-22 18:00:02', 'wins' => 1, 'losses' => 2,
            'games' => [
                // Lost to an OPEN opponent wallet: name the opponent's winning transaction.
                ['transaction_id' => 9042, 'payment_type' => 'loss', 'amount' => 100.0, 'level' => 2, 'created_at' => '2026-09-22 18:03:47',
                    'opponent' => ['competition_wallet_id' => 302, 'customer_id' => 45, 'wallet_status' => 1, 'transaction_id' => 9043]],
                // Lost to a CLOSED opponent wallet: KadiApi disputes its payout, no transaction_ids.
                ['transaction_id' => 9030, 'payment_type' => 'loss', 'amount' => 80.0, 'level' => 1, 'created_at' => '2026-09-22 18:03:00',
                    'opponent' => ['competition_wallet_id' => 320, 'customer_id' => 70, 'wallet_status' => 0, 'transaction_id' => 9031]],
                ['transaction_id' => 9017, 'payment_type' => 'win', 'amount' => 50.0, 'level' => 1, 'created_at' => '2026-09-22 18:02:10',
                    'opponent' => ['competition_wallet_id' => 318, 'customer_id' => 61, 'wallet_status' => 0, 'transaction_id' => 9016]],
                // Opponent unknown: cannot be reported.
                ['transaction_id' => 9001, 'payment_type' => 'loss', 'amount' => 20.0, 'level' => 1, 'created_at' => '2026-09-22 18:01:00', 'opponent' => null],
            ],
        ]],
        'jackpot_games' => [],
    ], $overrides);
}

/**
 * @param  array  $complaint  POST complaints response body
 * @param  array  $open  GET complaints `data`
 */
function fakeKadi(?array $complaint = null, int $status = 201, array $open = [], ?array $recent = null): void
{
    $complaint ??= ['success' => true, 'data' => [
        'id' => 3, 'complaint_id' => '9b1c2f0e-6a57-4f7e-9d0a-1f3c5b8e2a41', 'status' => 'pending_dispute',
    ]];

    Http::fake(function (Request $request) use ($complaint, $status, $open, $recent) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            str_contains($path, '/customers/played/recent/') => Http::response($recent ?? recentGamesBody()),
            $request->method() === 'GET' && str_ends_with($path, '/complaints') => Http::response(['data' => $open]),
            $request->method() === 'POST' && str_ends_with($path, '/complaints') => Http::response($complaint, $status),
            default => Http::response(['message' => 'Not found'], 404),
        };
    });
}

/**
 * The deadline the game server would give each fixture (created_at + 3 minutes), Nairobi time.
 * game:812 → GAME-5521; the tournament's rounds → TOURN-88 (level 2 = round 9042, level 1 = round 9030).
 */
const FIXTURE_DEADLINES = [
    'GAME-5521' => ['' => '2026-09-22 18:07:11'], // single games have no level
    'TOURN-88' => [2 => '2026-09-22 18:06:47', 1 => '2026-09-22 18:06:00'],
];

/** Stored like MySQL stores a timestamp: in UTC. */
function kadiTimestamp(string $nairobi): string
{
    return Carbon::parse($nairobi, 'Africa/Nairobi')->utc()->toDateTimeString();
}

/**
 * A throwaway stand-in for the game database's game_level_pending table. With no rows, the player's
 * (account 42) pending rows for the fixtures are seeded; rows without expires_at get their fixture's.
 */
function fakeGameLevelPending(?array $rows = null): void
{
    config(['database.connections.kadi' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('kadi');

    Schema::connection('kadi')->create('game_level_pending', function ($t) {
        $t->id();
        $t->integer('account_id');
        $t->string('game_type', 20)->default('1');
        $t->integer('price')->default(20);
        $t->integer('level')->default(1);
        $t->string('game_id', 100)->nullable();
        $t->timestamp('created_at')->nullable();
        $t->timestamp('expires_at')->nullable();
        $t->string('status')->default('pending');
    });

    $rows ??= [
        ['id' => 101, 'account_id' => 42, 'game_id' => 'GAME-5521'],
        ['id' => 102, 'account_id' => 42, 'game_id' => 'TOURN-88', 'level' => 2],
        ['id' => 103, 'account_id' => 42, 'game_id' => 'TOURN-88', 'level' => 1],
    ];

    foreach ($rows as $row) {
        $deadlines = FIXTURE_DEADLINES[$row['game_id'] ?? ''] ?? [];
        $row['expires_at'] = isset($row['expires_at'])
            ? kadiTimestamp($row['expires_at'])
            : ($deadlines !== [] ? kadiTimestamp($deadlines[$row['level'] ?? ''] ?? max($deadlines)) : null);

        DB::connection('kadi')->table('game_level_pending')->insert($row);
    }
}

function pendingStatus(int $id): string
{
    return DB::connection('kadi')->table('game_level_pending')->where('id', $id)->value('status');
}

function complaintPosted(): Closure
{
    return fn (Request $request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/complaints');
}

function reporter(): User
{
    return User::factory()->create(['linked_id' => 42]);
}

function historyFor(User $user)
{
    return Livewire::actingAs($user)->test(History::class)->call('load');
}

function submitGameReport($component, string $key, string $reason = 'The game froze or crashed', string $description = '')
{
    return $component->call('openReport', $key)
        ->set('reason', $reason)
        ->set('description', $description)
        ->call('submitReport');
}

beforeEach(function () {
    // The fixtures were played between 18:00 and 18:04:11 on 22 Sep; keep them inside the 3-minute report window.
    $this->travelTo(Carbon::parse('2026-09-22 18:05:00'));
    fakeGameLevelPending();
});

// --- Normalising KadiApi rows ------------------------------------------------

test('only lost games and lost rounds against a known opponent are reportable', function () {
    $items = PlayedGame::reportables(PlayedGame::listsFromApi(recentGamesBody(), 10));

    expect(array_keys($items))->toBe(['game:812', 'tournament-round:9042', 'tournament-round:9030']);

    expect($items['tournament-round:9042'])->toMatchArray([
        'competition_wallet_id' => 302, 'transaction_ids' => [9043], 'competition_id' => 'TOURN-88', 'game_wallet_id' => null,
    ])->and($items['tournament-round:9030']['transaction_ids'])->toBe([]);
});

test('lists are newest first, at most ten, and never keep opponents\' customer ids', function () {
    $rows = collect(range(1, 12))->map(fn ($i) => [
        'game_wallet_id' => $i, 'state' => 'loss', 'amount' => 10, 'created_at' => now()->subDays(20)->addDays($i)->toDateTimeString(),
    ])->all();

    $lists = PlayedGame::listsFromApi(recentGamesBody(['single_games' => $rows]), 10);

    expect($lists['game'])->toHaveCount(10)
        ->and($lists['game'][0]['game_wallet_id'])->toBe(12)
        ->and($lists['jackpot'])->toBe([])
        ->and(json_encode($lists))->not->toContain('customer_id');
});

// --- The page ---------------------------------------------------------------

test('guests are redirected from game history', function () {
    $this->get(route('games.history'))->assertRedirect(route('login'));
});

test('players can open game history', function () {
    $this->actingAs(reporter())->get(route('games.history'))->assertOk()->assertSee('Game History');
});

test('all three sections render, with Report only on lost games and reportable rounds', function () {
    fakeKadi();

    historyFor(reporter())
        ->assertSee('GAME-5521')
        ->assertSeeHtml("openReport('game:812')")
        ->assertDontSeeHtml("openReport('game:813')")
        ->call('setTab', 'tournament')
        ->assertSee('TOURN-88')
        ->assertSee('1 W')
        ->assertSee('2 L')
        ->assertSeeHtml("openReport('tournament-round:9042')")
        ->assertSeeHtml("openReport('tournament-round:9030')")
        ->assertDontSeeHtml("openReport('tournament-round:9017')")
        ->assertDontSeeHtml("openReport('tournament-round:9001')")
        ->call('setTab', 'jackpot')
        ->assertSee('No jackpots played yet.');
});

test('an unreachable KadiApi shows an error instead of an empty list', function () {
    Http::fake(['*' => Http::response('Bad gateway', 502)]);

    historyFor(reporter())
        ->assertSet('loadFailed', true)
        ->assertSee('We could not load your games right now.');
});

test('a player who is not linked to the game sees an empty history without calling KadiApi', function () {
    fakeKadi();

    historyFor(User::factory()->create(['linked_id' => null]))->assertSee('No games played yet.');

    Http::assertNothingSent();
});

test('items with an open KadiApi complaint show Under review instead of Report', function () {
    fakeKadi(open: [
        ['id' => 1, 'game_wallet_id' => 812, 'competition_wallet_id' => null, 'disputed_transactions' => [['transaction_id' => 77]]],
        ['id' => 2, 'game_wallet_id' => null, 'competition_wallet_id' => 302, 'disputed_transactions' => [['transaction_id' => 9043]]],
    ]);

    historyFor(reporter())
        ->assertSet('reportStates', ['game:812' => 'under_review', 'tournament-round:9042' => 'under_review'])
        ->assertSee('Under review')
        ->assertDontSeeHtml("openReport('game:812')");
});

// --- Filing ----------------------------------------------------------------

test('a lost single game is reported by its game wallet, recorded, and marks the player\'s pending rows', function () {
    fakeKadi();
    fakeGameLevelPending([
        ['id' => 1, 'account_id' => 42, 'game_id' => 'GAME-5521', 'status' => 'pending'],
        ['id' => 2, 'account_id' => 45, 'game_id' => 'GAME-5521', 'status' => 'pending'],  // opponent
        ['id' => 3, 'account_id' => 42, 'game_id' => 'GAME-5521', 'status' => 'approved'], // settled
        ['id' => 4, 'account_id' => 42, 'game_id' => 'OTHER', 'status' => 'pending'],      // another game
    ]);
    $user = reporter();

    submitGameReport(historyFor($user), 'game:812', description: 'It froze on my last card.')
        ->assertHasNoErrors()
        ->assertSet('showReportModal', false)
        ->assertSee('Complaint submitted.')
        ->assertSee('Reference: 9b1c2f0e-6a57-4f7e-9d0a-1f3c5b8e2a41')
        ->assertSet('reportStates.game:812', 'reported');

    Http::assertSent(fn (Request $request) => complaintPosted()($request)
        && $request->data() === ['customer_id' => 42, 'game_wallet_id' => 812, 'reason' => 'The game froze or crashed', 'description' => 'It froze on my last card.']
        && str_starts_with($request->header('Idempotency-Key')[0] ?? '', 'dispute-42-'));

    expect(GameDispute::sole())->toMatchArray([
        'user_id' => $user->id, 'subject_key' => 'game:812', 'game_wallet_id' => 812, 'game_id' => 'GAME-5521',
        'kadi_complaint_id' => 3, 'complaint_uuid' => '9b1c2f0e-6a57-4f7e-9d0a-1f3c5b8e2a41', 'status' => 'pending_dispute',
    ]);

    expect([pendingStatus(1), pendingStatus(2), pendingStatus(3), pendingStatus(4)])
        ->toBe(['disputed', 'pending', 'approved', 'pending']);
});

test('a lost round names the opponent\'s wallet and, while it is open, the opponent\'s winning transaction', function () {
    fakeKadi();
    fakeGameLevelPending([['id' => 1, 'account_id' => 42, 'game_id' => 'TOURN-88', 'status' => 'pending']]);

    submitGameReport(historyFor(reporter()), 'tournament-round:9042')->assertHasNoErrors();

    Http::assertSent(fn (Request $request) => complaintPosted()($request)
        && $request->data() === ['customer_id' => 42, 'competition_wallet_id' => 302, 'transaction_ids' => [9043], 'reason' => 'The game froze or crashed']);

    expect(GameDispute::sole())->toMatchArray(['kind' => 'tournament', 'competition_wallet_id' => 302, 'transaction_id' => 9042, 'opponent_transaction_id' => 9043])
        ->and(pendingStatus(1))->toBe('disputed');
});

test('a round lost to a closed wallet leaves transaction_ids out', function () {
    fakeKadi();

    submitGameReport(historyFor(reporter()), 'tournament-round:9030')->assertHasNoErrors();

    Http::assertSent(fn (Request $request) => complaintPosted()($request)
        && $request['competition_wallet_id'] === 320
        && ! array_key_exists('transaction_ids', $request->data()));
});

test('won games, won rounds, rounds without an opponent and forged keys are never sent', function (string $key) {
    fakeKadi();

    $result = app(GameDisputeService::class)->file(reporter(), $key, 'The game froze or crashed');

    expect($result->succeeded())->toBeFalse();
    Http::assertNotSent(complaintPosted());
})->with(['game:813', 'tournament-round:9017', 'tournament-round:9001', 'game:9999', 'tournament-round:301']);

test('"Other" sends the player\'s own words as the reason', function () {
    fakeKadi();

    historyFor(reporter())
        ->call('openReport', 'game:812')
        ->set('reason', GameDisputeService::OTHER_REASON)
        ->set('otherReason', 'Cards were dealt twice')
        ->call('submitReport')
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $request) => complaintPosted()($request) && $request['reason'] === 'Cards were dealt twice');
});

test('the form is validated before anything is sent', function () {
    fakeKadi();

    historyFor(reporter())
        ->call('openReport', 'game:812')
        ->call('submitReport')
        ->assertHasErrors(['reason' => 'required'])
        ->set('reason', 'Because I said so')
        ->call('submitReport')
        ->assertHasErrors(['reason' => 'in'])
        ->set('reason', GameDisputeService::OTHER_REASON)
        ->call('submitReport')
        ->assertHasErrors(['otherReason' => 'required'])
        ->set('otherReason', 'ab')
        ->call('submitReport')
        ->assertHasErrors(['otherReason' => 'min'])
        ->set('otherReason', 'Cards were dealt twice')
        ->set('description', str_repeat('a', 2001))
        ->call('submitReport')
        ->assertHasErrors(['description' => 'max']);

    Http::assertNotSent(complaintPosted());
});

// --- KadiApi responses ---------------------------------------------------------

test('a 422 that cannot be filed shows KadiApi\'s message as is and changes nothing', function () {
    fakeKadi(['success' => false, 'message' => 'This competition wallet has no payout to dispute.'], 422);
    fakeGameLevelPending([['id' => 1, 'account_id' => 42, 'game_id' => 'TOURN-88', 'status' => 'pending']]);

    submitGameReport(historyFor(reporter()), 'tournament-round:9030')
        ->assertSet('showReportModal', true)
        ->assertSet('reportError', 'This competition wallet has no payout to dispute.');

    expect(GameDispute::count())->toBe(0)
        ->and(pendingStatus(1))->toBe('pending');
});

test('KadiApi validation errors are shown next to the inputs', function () {
    fakeKadi(['message' => 'The reason field is invalid.', 'errors' => [
        'reason' => ['The reason field must be at least 3 characters.'],
        'description' => ['The description field must not be greater than 2000 characters.'],
    ]], 422);

    historyFor(reporter())
        ->call('openReport', 'game:812')
        ->set('reason', GameDisputeService::OTHER_REASON)
        ->set('otherReason', 'abc')
        ->call('submitReport')
        ->assertHasErrors(['otherReason', 'description'])
        ->assertSet('reportError', 'Please check the highlighted fields.');
});

test('a 409 already-open complaint shows Already reported and disables the action', function () {
    fakeKadi(['success' => false, 'message' => 'Transaction 9043 is already under an open complaint.'], 409);

    submitGameReport(historyFor(reporter()), 'tournament-round:9042')
        ->assertSet('showReportModal', false)
        ->assertSet('reportStates.tournament-round:9042', 'already_reported')
        ->assertSee('Already reported')
        ->assertDontSeeHtml("openReport('tournament-round:9042')");

    expect(GameDispute::count())->toBe(0);
});

test('other KadiApi failures are shown in the form', function (int $status, array $body, string $message) {
    fakeKadi($body, $status);

    submitGameReport(historyFor(reporter()), 'game:812')
        ->assertSet('showReportModal', true)
        ->assertSet('reportError', $message);

    expect(GameDispute::count())->toBe(0);
})->with([
    'idempotency key reused' => [409, ['success' => false, 'message' => 'Idempotency-Key reused with different request body'], 'Something changed while sending. Please submit your report again.'],
    'rate limited' => [429, ['message' => 'Too Many Attempts.'], 'Too many reports are being sent right now. Please try again shortly.'],
    'gateway error' => [502, ['message' => 'Bad gateway'], 'We could not confirm your report was received. Please try again in a minute.'],
]);

test('a game database outage does not undo a filed complaint', function () {
    fakeKadi();
    Schema::connection('kadi')->drop('game_level_pending');

    submitGameReport(historyFor(reporter()), 'game:812')
        ->assertSet('reportError', null)
        ->assertSee('Complaint submitted.');

    expect(GameDispute::count())->toBe(1);
});

// --- Never twice ----------------------------------------------------------------

test('the same submission always sends the same idempotency key, a changed form a new one', function () {
    $user = reporter();
    $item = PlayedGame::reportables(PlayedGame::listsFromApi(recentGamesBody(), 10))['game:812'];

    $key = GameDisputeService::idempotencyKey($user, GameDisputeService::payload($user, $item, 'The game froze or crashed', null));

    expect(GameDisputeService::idempotencyKey($user, GameDisputeService::payload($user, $item, 'The game froze or crashed', null)))->toBe($key)
        ->and(GameDisputeService::idempotencyKey($user, GameDisputeService::payload($user, $item, 'The game froze or crashed', 'more')))->not->toBe($key)
        ->and(strlen($key))->toBeLessThanOrEqual(64);
});

test('an item can be reported only once', function () {
    fakeKadi();
    $user = reporter();
    $disputes = app(GameDisputeService::class);

    expect($disputes->file($user, 'game:812', 'The game froze or crashed')->succeeded())->toBeTrue()
        ->and($disputes->file($user, 'game:812', 'The wrong result was recorded')->isAlreadyReported())->toBeTrue();

    expect(collect(Http::recorded())->filter(fn ($pair) => complaintPosted()($pair[0])))->toHaveCount(1);
});

// --- The 3-minute report window ---------------------------------------------

test('each reportable item can be reported until 3 minutes after it was played', function () {
    $items = PlayedGame::reportables(PlayedGame::listsFromApi(recentGamesBody(), 10));

    expect($items['game:812']['report_expires_at'])->toBe('2026-09-22 18:07:11')
        ->and($items['tournament-round:9042']['report_expires_at'])->toBe('2026-09-22 18:06:47');
});

test('the page tells players about the 3-minute window and counts down the time left', function () {
    fakeKadi();

    historyFor(reporter())
        ->assertSee('within 3 minutes of playing')
        ->assertSee('2 minutes left')
        ->assertSeeHtml('data-seconds-left="131"')
        ->assertSeeHtml("openReport('game:812')");
});

test('past 3 minutes the Report button disappears and filing is refused without calling KadiApi', function () {
    fakeKadi();
    $this->travelTo(Carbon::parse('2026-09-22 18:07:12')); // one second after game:812's window closed
    $user = reporter();

    historyFor($user)
        ->assertDontSeeHtml("openReport('game:812')")
        ->assertSee('Reporting closed')
        ->call('openReport', 'game:812')
        ->assertSet('showReportModal', false)
        ->assertSee('within 3 minutes of playing');

    $result = app(GameDisputeService::class)->file($user, 'game:812', 'The game froze or crashed');

    expect($result->succeeded())->toBeFalse()
        ->and($result->message)->toContain('within 3 minutes')
        ->and(GameDispute::count())->toBe(0);
    Http::assertNotSent(complaintPosted());
});

test('a game can still be reported in the last second of its 3 minutes', function () {
    fakeKadi();
    $this->travelTo(Carbon::parse('2026-09-22 18:07:10'));

    expect(app(GameDisputeService::class)->file(reporter(), 'game:812', 'The game froze or crashed')->succeeded())->toBeTrue();
});

test('a game without a play time cannot be reported when the game database is down', function () {
    $body = recentGamesBody();
    unset($body['single_games'][0]['created_at']);
    fakeKadi(recent: $body);
    Schema::connection('kadi')->drop('game_level_pending'); // no deadline from the game server either

    $result = app(GameDisputeService::class)->file(reporter(), 'game:812', 'The game froze or crashed');

    expect($result->succeeded())->toBeFalse();
    Http::assertNotSent(complaintPosted());
});

test('a game without a play time follows the game server\'s deadline', function () {
    $body = recentGamesBody();
    unset($body['single_games'][0]['created_at']);
    fakeKadi(recent: $body);

    expect(app(GameDisputeService::class)->file(reporter(), 'game:812', 'The game froze or crashed')->succeeded())->toBeTrue();
});

test('an item reported earlier keeps its report state after the window closes', function () {
    fakeKadi(open: [['id' => 1, 'game_wallet_id' => 812, 'competition_wallet_id' => null, 'disputed_transactions' => []]]);
    $this->travelTo(Carbon::parse('2026-09-30 12:00:00'));

    historyFor(reporter())
        ->assertSet('reportStates.game:812', 'under_review')
        ->assertSee('Under review');
});

test('a player can send only a few reports in a short time', function () {
    config(['kadi.game_disputes.max_attempts' => 2]);
    fakeKadi(['success' => false, 'message' => 'This game has no winner payout to dispute.'], 422);
    $user = reporter();
    $disputes = app(GameDisputeService::class);

    $disputes->file($user, 'game:812', 'The game froze or crashed');
    $disputes->file($user, 'tournament-round:9042', 'The game froze or crashed');
    $third = $disputes->file($user, 'tournament-round:9030', 'The game froze or crashed');

    expect($third->message)->toContain('sent several reports recently');
    expect(collect(Http::recorded())->filter(fn ($pair) => complaintPosted()($pair[0])))->toHaveCount(2);
});

test('the window follows the config and is described in words', function (int $minutes, string $label, string $expires) {
    config(['kadi.game_disputes.report_window_minutes' => $minutes]);

    $items = PlayedGame::reportables(PlayedGame::listsFromApi(recentGamesBody(), 10));

    expect(PlayedGame::reportWindowLabel())->toBe($label)
        ->and($items['game:812']['report_expires_at'])->toBe($expires);
})->with([
    'default' => [3, '3 minutes', '2026-09-22 18:07:11'],
    'one minute' => [1, '1 minute', '2026-09-22 18:05:11'],
    'an hour' => [60, '1 hour', '2026-09-22 19:04:11'],
]);

// --- The game server's deadline (kadi.game_level_pending.expires_at) ------------------

test('the deadline is the pending row\'s expires_at, not KadiApi\'s created_at', function () {
    fakeKadi();
    // The game server gave this game longer than created_at + 3 minutes.
    fakeGameLevelPending([['id' => 1, 'account_id' => 42, 'game_id' => 'GAME-5521', 'expires_at' => '2026-09-22 18:10:00']]);
    $this->travelTo(Carbon::parse('2026-09-22 18:08:00')); // past created_at + 3 minutes

    historyFor(reporter())
        ->assertSeeHtml("openReport('game:812')")
        ->assertSeeHtml('data-seconds-left="120"');

    expect(app(GameDisputeService::class)->file(reporter(), 'game:812', 'The game froze or crashed')->succeeded())->toBeTrue();
});

test('an earlier expires_at closes reporting early', function () {
    fakeKadi();
    fakeGameLevelPending([['id' => 1, 'account_id' => 42, 'game_id' => 'GAME-5521', 'expires_at' => '2026-09-22 18:04:59']]);

    historyFor(reporter())->assertDontSeeHtml("openReport('game:812')")->assertSee('Reporting closed');
});

test('without a pending row the game cannot be reported, even inside 3 minutes', function (array $row) {
    fakeKadi();
    fakeGameLevelPending([$row]);
    $user = reporter();

    historyFor($user)->assertDontSeeHtml("openReport('game:812')");

    expect(app(GameDisputeService::class)->file($user, 'game:812', 'The game froze or crashed')->succeeded())->toBeFalse();
    Http::assertNotSent(complaintPosted());
})->with([
    'already paid out' => [['id' => 1, 'account_id' => 42, 'game_id' => 'GAME-5521', 'status' => 'approved']],
    'cancelled' => [['id' => 1, 'account_id' => 42, 'game_id' => 'GAME-5521', 'status' => 'cancelled']],
    'only the opponent has a row' => [['id' => 1, 'account_id' => 45, 'game_id' => 'GAME-5521']],
    'another game' => [['id' => 1, 'account_id' => 42, 'game_id' => 'OTHER']],
]);

test('a round uses the pending row of its own level', function () {
    fakeKadi();
    fakeGameLevelPending([
        ['id' => 1, 'account_id' => 42, 'game_id' => 'TOURN-88', 'level' => 2, 'expires_at' => '2026-09-22 18:09:00'],
        ['id' => 2, 'account_id' => 42, 'game_id' => 'TOURN-88', 'level' => 1, 'expires_at' => '2026-09-22 18:05:30'],
    ]);

    $items = PlayedGame::reportables(app(GameDisputeService::class)->recentGames(reporter()));

    expect($items['tournament-round:9042']['report_expires_at'])->toBe('2026-09-22 18:09:00')
        ->and($items['tournament-round:9030']['report_expires_at'])->toBe('2026-09-22 18:05:30');
});

test('if the game database cannot be read, KadiApi\'s created_at + 3 minutes is used', function () {
    fakeKadi();
    Schema::connection('kadi')->drop('game_level_pending');

    $items = PlayedGame::reportables(app(GameDisputeService::class)->recentGames(reporter()));

    expect($items['game:812']['report_expires_at'])->toBe('2026-09-22 18:07:11');
});
