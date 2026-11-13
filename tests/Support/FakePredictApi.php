<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\KadiApiService;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A stateful stand-in for the Kadi API's prediction routes (the API's PredictMarketController, BetService and
 * PoolMath), behind Http::fake: markets, wallets in cents with versions, quotes (10 seconds, one use, tied to the
 * player, side and stake) and bets kept by reference, so a test can check that money moves once and only once.
 *
 * Pool markets price by the API's rule since PR #63: 1 + other side × (1 − cut) / side, cut 20% of the losing side.
 *
 * `next($action, ...)` scripts the next answers to `quote` or `bet`: 'timeout' (nothing happens, no answer),
 * 'lost-answer' (it happens, no answer), or [status, code].
 */
class FakePredictApi
{
    public const URL = 'https://kadi-api.test/api/v1/';

    public const SITE_KEY = 'predict-site-key-for-tests';

    /** @var array<int, array<string, mixed>> market id => market as the API shows it */
    public array $markets = [];

    /** @var array<int, array{yes: int, no: int}> pool market id => stakes in cents (seed included) */
    public array $pools = [];

    /** @var array<int, int> customer id => balance in cents */
    public array $balances = [];

    /** @var array<int, int> */
    public array $versions = [];

    /** @var array<string, array<string, mixed>> quote id => quote */
    public array $quotes = [];

    /** @var array<string, array<string, mixed>> reference => bet */
    public array $bets = [];

    /** @var list<array{action: string, body: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<string, list<string|array{0: int, 1: string}>> */
    private array $scripted = ['quote' => [], 'bet' => []];

    private int $nextCustomer = 7000;

    private int $nextBet = 1;

    public static function install(): self
    {
        $fake = new self;

        config(['services.kadi_api.url' => self::URL, 'services.kadi_api.key' => self::SITE_KEY]);
        app()->forgetInstance(KadiApiService::class);
        Http::fake([self::URL.'*' => fn (Request $request) => $fake->handle($request)]);

        return $fake;
    }

    /** A verified player linked to a fake customer holding this balance. */
    public function player(int $cents = 100_000, array $attributes = []): User
    {
        $customer = $this->nextCustomer++;
        $this->balances[$customer] = $cents;
        $this->versions[$customer] = 1;

        return User::factory()->create(['linked_id' => $customer, 'email_verified_at' => now(), ...$attributes]);
    }

    public function balanceOf(User $user): int
    {
        return $this->balances[(int) $user->linked_id];
    }

    /** An open pool market seeded at $yesShare (KES 1,000 notional, like an import). */
    public function poolMarket(int $id, float $yesShare = 0.5, array $changes = []): self
    {
        $this->pools[$id] = ['yes' => (int) round(100_000 * $yesShare), 'no' => 100_000 - (int) round(100_000 * $yesShare)];
        $this->markets[$id] = array_replace_recursive($this->base($id, 'pool'), $changes);

        return $this;
    }

    /** An open fixed-odds (reference) market quoting these multipliers. */
    public function fixedMarket(int $id, float $yes = 1.58, float $no = 2.55, array $changes = []): self
    {
        $this->markets[$id] = array_replace_recursive($this->base($id, 'reference'), [
            'odds' => ['yes_multiplier' => $yes, 'no_multiplier' => $no, 'is_reference' => true, 'attribution' => 'Reference prices from Polymarket'],
        ], $changes);

        return $this;
    }

    /**
     * @param  'timeout'|'lost-answer'|array{0: int, 1: string}  ...$answers
     */
    public function next(string $action, string|array ...$answers): self
    {
        array_push($this->scripted[$action], ...$answers);

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function callsTo(string $action): array
    {
        return array_values(array_map(fn (array $call) => $call['body'], array_filter($this->calls, fn (array $call) => $call['action'] === $action)));
    }

    /** @return list<array<string, mixed>> this player's bets, newest first */
    public function betsOf(User $user): array
    {
        return array_values(array_reverse(array_filter($this->bets, fn (array $bet) => $bet['customer'] === (int) $user->linked_id)));
    }

    private function handle(Request $request): PromiseInterface|Closure
    {
        $path = Str::before(Str::after($request->url(), self::URL), '?');
        $body = $request->method() === 'GET' ? $request->data() : (json_decode($request->body(), true) ?: []);

        [$action, $run] = match (true) {
            $path === 'predict/markets' => ['markets', fn () => $this->ok(['items' => array_values(array_map(fn (int $id) => $this->show($id), array_keys($this->markets))), 'pagination' => ['page' => 1, 'per_page' => 24, 'total' => count($this->markets), 'last_page' => 1]])],
            (bool) preg_match('#^predict/markets/(\d+)/history$#', $path, $m) => ['history', fn () => $this->ok(['market_id' => (int) $m[1], 'points' => []])],
            (bool) preg_match('#^predict/markets/(\d+)/estimate$#', $path, $m) => ['estimate', fn () => $this->estimate((int) $m[1], $body)],
            (bool) preg_match('#^predict/markets/(\d+)$#', $path, $m) => ['market', fn () => isset($this->markets[(int) $m[1]]) ? $this->ok($this->show((int) $m[1])) : $this->refuse(404, 'market_not_found')],
            $path === 'predict/updates' => ['updates', fn () => $this->ok(['server_time' => Date::now()->toIso8601String(), 'has_more' => false, 'reference_prices_at' => Date::now()->toIso8601String(), 'markets' => []])],
            (bool) preg_match('#^customers/([^/]+)/predict/quotes$#', $path, $m) => ['quote', fn () => $this->quote($this->customer($m[1]), $body)],
            (bool) preg_match('#^customers/([^/]+)/predict/bets$#', $path, $m) && $request->method() === 'POST' => ['bet', fn () => $this->bet($this->customer($m[1]), $body)],
            (bool) preg_match('#^customers/([^/]+)/predict/bets$#', $path, $m) => ['bets', fn () => $this->listBets($this->customer($m[1]), $body)],
            (bool) preg_match('#^customers/([^/]+)$#', $path, $m) => ['customer', fn () => $this->ok(['id' => $this->customer($m[1]), 'balance' => $this->balances[$this->customer($m[1])] / 100])],
            default => ['unknown', fn () => Http::response(['message' => 'Not faked: '.$path], 404)],
        };

        $this->calls[] = ['action' => $action, 'body' => $body];
        $scripted = isset($this->scripted[$action]) ? array_shift($this->scripted[$action]) : null;

        if ($scripted === 'timeout') {
            return Http::failedConnection();
        }

        if ($scripted === 'lost-answer') {
            $run();

            return Http::failedConnection();
        }

        if (is_array($scripted)) {
            return $this->refuse($scripted[0], $scripted[1]);
        }

        return $run();
    }

    private function quote(int $customer, array $body): PromiseInterface
    {
        $market = $this->markets[(int) $body['market_id']] ?? null;

        if ($market === null) {
            return $this->refuse(404, 'market_not_found');
        }

        if ($market['engine'] === 'pool') {
            return $this->refuse(409, 'quote_not_needed');
        }

        $id = (string) Str::uuid();
        $stake = (float) $body['stake'];
        $multiplier = (float) $market['odds'][$body['side'].'_multiplier'];
        $this->quotes[$id] = [
            'quote_id' => $id, 'customer' => $customer, 'market_id' => $market['id'], 'side' => $body['side'], 'stake' => $stake,
            'multiplier' => $multiplier, 'potential_payout' => round($stake * $multiplier, 2), 'price' => 0.6,
            'expires_at' => Date::now()->addSeconds(10)->toIso8601String(), 'used' => false,
        ];

        return $this->ok(['quote' => array_diff_key($this->quotes[$id], ['customer' => 1, 'used' => 1]), 'replayed' => false], 201);
    }

    private function bet(int $customer, array $body): PromiseInterface
    {
        $reference = (string) $body['reference'];
        $stakeCents = (int) round((float) $body['stake'] * 100);

        // The reference first: the same bet again is a replay, anything else under it a conflict.
        if (isset($this->bets[$reference])) {
            $bet = $this->bets[$reference];

            return $bet['customer'] === $customer && $bet['market_id'] === (int) $body['market_id'] && $bet['side'] === $body['side'] && $bet['stake_cents'] === $stakeCents
                ? $this->ok(['bet' => $this->betView($bet), 'balance' => null, 'replayed' => true])
                : $this->refuse(409, 'reference_conflict');
        }

        $market = $this->markets[(int) $body['market_id']] ?? null;

        if ($market === null) {
            return $this->refuse(404, 'market_not_found');
        }

        $multiplier = null;

        if ($market['engine'] !== 'pool') {
            $quote = $this->quotes[$body['quote_id'] ?? ''] ?? null;

            if ($quote === null) {
                return $this->refuse(422, 'quote_required');
            }

            if ($quote['used']) {
                return $this->refuse(409, 'quote_used');
            }

            if (Date::parse($quote['expires_at'])->lessThanOrEqualTo(Date::now())) {
                return $this->refuse(409, 'quote_expired');
            }

            if ($quote['customer'] !== $customer || $quote['market_id'] !== $market['id'] || $quote['side'] !== $body['side'] || (int) round($quote['stake'] * 100) !== $stakeCents) {
                return $this->refuse(409, 'quote_mismatch');
            }

            $multiplier = $quote['multiplier'];
        }

        if (($this->balances[$customer] ?? 0) < $stakeCents) {
            return $this->refuse(402, 'insufficient_funds');
        }

        if (isset($quote)) {
            $this->quotes[$quote['quote_id']]['used'] = true;
        }

        if ($market['engine'] === 'pool') {
            $this->pools[$market['id']][$body['side']] += $stakeCents;
            $multiplier = $this->poolMultiplier($market['id'], $body['side']);
        }

        $this->balances[$customer] -= $stakeCents;
        $this->versions[$customer]++;
        $this->bets[$reference] = [
            'id' => $this->nextBet++, 'customer' => $customer, 'reference' => $reference, 'market_id' => $market['id'], 'side' => $body['side'],
            'stake_cents' => $stakeCents, 'multiplier' => $multiplier, 'is_estimate' => $market['engine'] === 'pool', 'status' => 'open',
            'placed_at' => Date::now()->toIso8601String(),
        ];

        return $this->ok([
            'bet' => $this->betView($this->bets[$reference]),
            'balance' => ['balance' => $this->balances[$customer] / 100, 'wallet_version' => $this->versions[$customer]],
            'replayed' => false,
        ], 201);
    }

    private function listBets(int $customer, array $query): PromiseInterface
    {
        $status = $query['status'] ?? null;
        $bets = array_values(array_filter(array_reverse($this->bets), fn (array $bet) => $bet['customer'] === $customer
            && ($status === null || ($status === 'settled' ? $bet['status'] !== 'open' : $bet['status'] === $status))));

        return $this->ok(['items' => array_map($this->betView(...), $bets), 'pagination' => ['page' => 1, 'per_page' => 20, 'total' => count($bets), 'last_page' => 1]]);
    }

    private function estimate(int $id, array $query): PromiseInterface
    {
        $market = $this->markets[$id] ?? null;
        $stake = (float) ($query['stake'] ?? 0);

        if ($market === null) {
            return $this->refuse(404, 'market_not_found');
        }

        $multiplier = $market['engine'] === 'pool'
            ? $this->poolMultiplier($id, (string) $query['side'], (int) round($stake * 100))
            : (float) $market['odds'][$query['side'].'_multiplier'];

        return $this->ok(['side' => $query['side'], 'stake' => $stake, 'estimated_multiplier' => $multiplier, 'estimated_payout' => round($stake * $multiplier, 2), 'is_estimate' => true, 'replayed' => false]);
    }

    /** The API's pool rule since PR #63: 1 + other × (1 − 0.2) / side, rounded down to a hundredth. */
    private function poolMultiplier(int $id, string $side, int $extraCents = 0): float
    {
        $pool = $this->pools[$id];
        $mine = $pool[$side] + $extraCents;
        $other = $pool[$side === 'yes' ? 'no' : 'yes'];

        return floor((1 + $other * 0.8 / $mine) * 100) / 100;
    }

    /** @return array<string, mixed> */
    private function show(int $id): array
    {
        $market = $this->markets[$id];

        if ($market['engine'] === 'pool') {
            $pool = $this->pools[$id];
            $total = $pool['yes'] + $pool['no'];
            $market['odds'] = array_replace($market['odds'], [
                'yes_probability' => round($pool['yes'] / $total, 4), 'no_probability' => round($pool['no'] / $total, 4),
                'yes_multiplier' => $this->poolMultiplier($id, 'yes'), 'no_multiplier' => $this->poolMultiplier($id, 'no'),
            ]);
        }

        return $market;
    }

    /** @return array<string, mixed> */
    private function base(int $id, string $engine): array
    {
        return [
            'id' => $id, 'source' => 'polymarket', 'engine' => $engine, 'status' => 'open', 'question' => "Fake question {$id}?",
            'description' => 'Resolves Yes if it happens.', 'resolution_criteria' => 'Resolves Yes if it happens.', 'resolution_source' => null,
            'category' => 'sport', 'image_url' => null, 'external_url' => null, 'event' => null, 'timezone' => 'Africa/Nairobi', 'currency' => 'KES',
            'close_at' => Date::now()->addDays(3)->toIso8601String(), 'betting_closes_at' => Date::now()->addDays(2)->toIso8601String(),
            'betting_available' => true, 'resolution_deadline_at' => Date::now()->addDays(5)->toIso8601String(), 'result' => null,
            'min_stake' => 10, 'max_stake' => 5000, 'max_payout' => 250000,
            'odds' => ['yes_label' => 'Yes', 'no_label' => 'No', 'yes_probability' => 0.5, 'no_probability' => 0.5, 'yes_multiplier' => 1.8, 'no_multiplier' => 1.8,
                'is_estimate' => true, 'is_reference' => $engine === 'reference', 'volume' => 0, 'bets_count' => 0, 'updated_at' => Date::now()->toIso8601String()],
        ];
    }

    /** @return array<string, mixed> */
    private function betView(array $bet): array
    {
        $stake = $bet['stake_cents'] / 100;

        return [
            'id' => $bet['id'], 'reference' => $bet['reference'], 'market_id' => $bet['market_id'], 'question' => $this->markets[$bet['market_id']]['question'] ?? null,
            'market_status' => 'open', 'side' => $bet['side'], 'currency' => 'KES', 'stake' => $stake, 'status' => $bet['status'],
            'multiplier' => $bet['multiplier'], 'potential_payout' => $bet['multiplier'] !== null ? round($stake * $bet['multiplier'], 2) : null,
            'payout' => null, 'is_estimate' => $bet['is_estimate'], 'placed_at' => $bet['placed_at'], 'settled_at' => null,
        ];
    }

    private function customer(string $encrypted): int
    {
        return (int) decryptOpenSSL($encrypted);
    }

    private function ok(array $data, int $status = 200): PromiseInterface
    {
        return Http::response(['success' => true, 'data' => $data], $status);
    }

    private function refuse(int $status, string $code): PromiseInterface
    {
        return Http::response(['success' => false, 'message' => 'Refused: '.$code, 'code' => $code, 'retryable' => $status >= 500 || $status === 429], $status);
    }
}
