<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\KadiApiService;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A stateful stand-in for the Kadi API's wager endpoints (docs in the API repo, WagerService), behind Http::fake:
 * wallets in cents with versions, launch tokens, player tokens, wagers with settlement versions, tombstones and
 * winnings tax, so sports money tests can check balances the way the API would move them.
 *
 * `next($action, ...)` scripts the next answer to an action (place, settle, reverse, lookup, redeem, launch):
 * 'timeout' (nothing happens, no answer), 'lost-answer' (it happens, no answer), or an HTTP status with a code.
 */
class FakeWagerApi
{
    public const URL = 'https://kadi-api.test/api/v1/';

    public const SITE_KEY = 'site-key-for-tests';

    public const GAME_KEY = 'sportsbook-game-key-for-tests';

    /** @var array<int, int> customer id => balance in cents */
    public array $balances = [];

    /** @var array<int, int> */
    public array $versions = [];

    /** @var array<int, string> customer id => break end (ISO 8601) */
    public array $breaks = [];

    /** @var array<string, array<string, mixed>> "product|reference" => wager */
    public array $wagers = [];

    /** @var array<string, int> launch token => customer id */
    private array $launchTokens = [];

    /** @var array<string, int> player token => customer id */
    public array $playerTokens = [];

    /** @var array<string, float> product => winnings tax rate */
    public array $taxRates = [];

    /** @var array<string, list<string|array{0: int, 1: string}>> */
    private array $scripted = [];

    /** @var list<array{action: string, body: array<string, mixed>, headers: array<string, mixed>}> */
    public array $calls = [];

    private int $nextCustomer = 9000;

    public static function install(): self
    {
        $fake = new self;

        config([
            'services.kadi_api.url' => self::URL,
            'services.kadi_api.key' => self::SITE_KEY,
            'services.kadi_api.sportsbook_game_key' => self::GAME_KEY,
            'platform.sports_betting' => true,
        ]);
        app()->forgetInstance(KadiApiService::class);

        Http::fake([self::URL.'*' => fn (Request $request) => $fake->handle($request)]);

        return $fake;
    }

    /** A verified player linked to a fake API customer holding this balance. */
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

    public function setBalance(User $user, int $cents): void
    {
        $this->balances[(int) $user->linked_id] = $cents;
        $this->versions[(int) $user->linked_id]++;
    }

    /**
     * @param  'timeout'|'lost-answer'|array{0: int, 1: string}  ...$answers
     */
    public function next(string $action, string|array ...$answers): self
    {
        array_push($this->scripted[$action], ...$answers);

        return $this;
    }

    /** @return list<array{action: string, body: array<string, mixed>, headers: array<string, mixed>}> */
    public function callsTo(string $action): array
    {
        return array_values(array_filter($this->calls, fn (array $call): bool => $call['action'] === $action));
    }

    /** @return array<string, mixed>|null */
    public function wager(string $product, ?string $reference): ?array
    {
        return $this->wagers["{$product}|{$reference}"] ?? null;
    }

    public function __construct()
    {
        foreach (['launch', 'redeem', 'place', 'settle', 'reverse', 'lookup', 'revoke', 'responsible'] as $action) {
            $this->scripted[$action] = [];
        }
    }

    private function handle(Request $request): PromiseInterface|Closure
    {
        $path = Str::after($request->url(), self::URL);
        $path = Str::before($path, '?');
        $body = $request->method() === 'GET' ? $request->data() : (json_decode($request->body(), true) ?: []);

        [$action, $run] = match (true) {
            (bool) preg_match('#^customers/([^/]+)/launch-tokens$#', $path, $m) => ['launch', fn () => $this->launch($m[1], $body)],
            (bool) preg_match('#^customers/([^/]+)/responsible-gambling$#', $path, $m) => ['responsible', fn () => $this->responsible($m[1])],
            (bool) preg_match('#^customers/([^/]+)/player-tokens/revoke$#', $path, $m) => ['revoke', fn () => $this->revoke($m[1])],
            $path === 'launch-tokens/redeem' => ['redeem', fn () => $this->redeem($request, $body)],
            $path === 'wagers' => ['place', fn () => $this->place($request, $body)],
            (bool) preg_match('#^wagers/([^/]+)/settle$#', $path, $m) => ['settle', fn () => $this->settle($request, rawurldecode($m[1]), $body)],
            (bool) preg_match('#^wagers/([^/]+)/reverse$#', $path, $m) => ['reverse', fn () => $this->reverse($request, rawurldecode($m[1]), $body)],
            (bool) preg_match('#^wagers/([^/]+)$#', $path, $m) => ['lookup', fn () => $this->lookup($request, rawurldecode($m[1]), $body)],
            default => ['unknown', fn () => Http::response(['message' => 'Not faked: '.$path], 404)],
        };

        $this->calls[] = ['action' => $action, 'body' => $body, 'headers' => $request->headers()];
        $scripted = $action === 'unknown' ? null : array_shift($this->scripted[$action]);

        if ($scripted === 'timeout') {
            return Http::failedConnection();
        }

        if ($scripted === 'lost-answer') {
            $run();

            return Http::failedConnection();
        }

        if (is_array($scripted)) {
            return Http::response(['success' => false, 'message' => 'Scripted', 'code' => $scripted[1]], $scripted[0]);
        }

        return $run();
    }

    private function customer(string $encrypted): int
    {
        return (int) decryptOpenSSL($encrypted);
    }

    private function launch(string $encrypted, array $body): PromiseInterface
    {
        $customer = $this->customer($encrypted);

        if (isset($this->breaks[$customer])) {
            return Http::response(['success' => false, 'code' => 'player_excluded'], 403);
        }

        $token = Str::random(40);
        $this->launchTokens[$token] = $customer;

        return Http::response(['success' => true, 'data' => ['token' => $token, 'launch_url' => 'https://sports.kadi.online', 'expires_at' => now()->addMinutes(2)->toIso8601String()]], 201);
    }

    private function responsible(string $encrypted): PromiseInterface
    {
        $customer = $this->customer($encrypted);
        $endsAt = $this->breaks[$customer] ?? null;

        return Http::response(['success' => true, 'data' => [
            'status' => 1, 'can_play' => $endsAt === null, 'exclusion' => $endsAt === null ? null : ['type' => 'break', 'ends_at' => $endsAt],
            'deposit_limits' => [], 'deposit_remaining' => null,
        ]]);
    }

    private function revoke(string $encrypted): PromiseInterface
    {
        $customer = $this->customer($encrypted);
        $before = count($this->playerTokens);
        $this->playerTokens = array_filter($this->playerTokens, fn (int $owner): bool => $owner !== $customer);

        return Http::response(['success' => true, 'data' => ['revoked' => $before - count($this->playerTokens)]]);
    }

    private function gameKeyRefusal(Request $request): ?PromiseInterface
    {
        return ($request->header('X-API-KEY')[0] ?? null) === self::GAME_KEY
            ? null
            : Http::response(['message' => 'This route needs a game API key', 'code' => 'game_key_required'], 403);
    }

    private function redeem(Request $request, array $body): PromiseInterface
    {
        if ($refusal = $this->gameKeyRefusal($request)) {
            return $refusal;
        }

        $customer = $this->launchTokens[$body['token'] ?? ''] ?? null;

        if ($customer === null) {
            return Http::response(['success' => false, 'code' => 'invalid_launch_token'], 401);
        }

        unset($this->launchTokens[$body['token']]);
        $token = 'pt|'.Str::random(40);
        $this->playerTokens[$token] = $customer;

        return Http::response(['success' => true, 'data' => [
            'player_token' => $token,
            'expires_at' => now()->addHours(12)->toIso8601String(),
            'customer' => ['id' => $customer],
            'balance' => $this->balanceBlock($customer),
        ]]);
    }

    private function place(Request $request, array $body): PromiseInterface
    {
        if ($refusal = $this->gameKeyRefusal($request)) {
            return $refusal;
        }

        $token = Str::after($request->header('Authorization')[0] ?? '', 'Bearer ');
        $customer = $this->playerTokens[$token] ?? null;

        if ($customer === null) {
            return Http::response(['success' => false, 'code' => 'invalid_player_token'], 401);
        }

        $key = "{$body['product']}|{$body['reference']}";
        $freeBet = $body['funding'] === 'free_bet';
        $stake = $freeBet ? 0 : $this->cents($body['stake']);
        $freeAmount = $freeBet ? $this->cents($body['free_bet_amount']) : 0;

        if (isset($this->wagers[$key])) {
            $existing = $this->wagers[$key];

            if ($existing['tombstone']) {
                return Http::response(['success' => false, 'code' => 'wager_voided'], 409);
            }

            if ($existing['customer'] !== $customer || $existing['stake'] !== $stake || $existing['free_bet_amount'] !== $freeAmount) {
                return Http::response(['success' => false, 'code' => 'reference_conflict'], 409);
            }

            return Http::response(['success' => true, 'data' => ['wager' => $this->wagerResource($key), 'balance' => $this->balanceBlock($customer), 'replayed' => true]]);
        }

        if (isset($this->breaks[$customer])) {
            return Http::response(['success' => false, 'code' => 'player_excluded', 'excluded_until' => $this->breaks[$customer]], 403);
        }

        if (! $freeBet && $this->balances[$customer] < $stake) {
            return Http::response(['success' => false, 'code' => 'insufficient_funds'], 422);
        }

        $this->move($customer, -$stake);
        $this->wagers[$key] = [
            'product' => $body['product'], 'reference' => $body['reference'], 'customer' => $customer, 'funding' => $body['funding'],
            'stake' => $stake, 'free_bet_amount' => $freeAmount, 'status' => 'open', 'version' => 0, 'tombstone' => false, 'settlements' => [],
        ];

        return Http::response(['success' => true, 'data' => ['wager' => $this->wagerResource($key), 'balance' => $this->balanceBlock($customer), 'replayed' => false]], 201);
    }

    private function settle(Request $request, string $reference, array $body): PromiseInterface
    {
        if ($refusal = $this->gameKeyRefusal($request)) {
            return $refusal;
        }

        $key = "{$body['product']}|{$reference}";
        $version = (int) $body['version'];
        $outcome = $body['outcome'];
        $payout = $this->cents($body['payout']);
        $bonus = $this->cents($body['bonus'] ?? 0);

        if ($outcome !== 'won' && ($payout !== 0 || $bonus !== 0)) {
            return Http::response(['success' => false, 'code' => 'validation_failed'], 422);
        }

        if (! isset($this->wagers[$key])) {
            if ($outcome !== 'void') {
                return Http::response(['success' => false, 'code' => 'wager_not_found'], 404);
            }

            $this->wagers[$key] = [
                'product' => $body['product'], 'reference' => $reference, 'customer' => null, 'funding' => 'cash', 'stake' => 0, 'free_bet_amount' => 0,
                'status' => 'void', 'version' => $version, 'tombstone' => true,
                'settlements' => [$version => ['outcome' => 'void', 'payout' => 0, 'bonus' => 0, 'tax' => 0, 'credited' => 0, 'status' => 'applied']],
            ];

            return Http::response(['success' => true, 'data' => ['wager' => $this->wagerResource($key), 'settlement' => $this->settlementResource($key, $version), 'replayed' => false]], 201);
        }

        $wager = &$this->wagers[$key];
        $existing = $wager['settlements'][$version] ?? null;

        if ($existing !== null && $existing['status'] === 'applied') {
            return $existing['outcome'] === $outcome && $existing['payout'] === $payout && $existing['bonus'] === $bonus
                ? Http::response(['success' => true, 'data' => ['wager' => $this->wagerResource($key), 'settlement' => $this->settlementResource($key, $version), 'replayed' => true]])
                : Http::response(['success' => false, 'code' => 'settlement_conflict', 'status' => $wager['status'], 'settlement_version' => $wager['version']], 409);
        }

        if ($wager['status'] !== 'open' || $version !== $wager['version'] + 1) {
            return Http::response(['success' => false, 'code' => 'version_out_of_order', 'status' => $wager['status'], 'settlement_version' => $wager['version']], 409);
        }

        $freeBet = $wager['funding'] === 'free_bet';
        $rate = $this->taxRates[$wager['product']] ?? 0.0;
        $base = $outcome === 'won' ? ($freeBet ? $payout + $bonus : max(0, $payout + $bonus - $wager['stake'])) : 0;
        $tax = (int) round($base * $rate);
        $credited = match ($outcome) {
            'won' => $payout + $bonus - $tax,
            'void' => $freeBet ? 0 : $wager['stake'],
            default => 0,
        };

        $this->move($wager['customer'], $credited);
        $wager['settlements'][$version] = ['outcome' => $outcome, 'payout' => $payout, 'bonus' => $bonus, 'tax' => $tax, 'credited' => $credited, 'status' => 'applied'];
        $wager['status'] = $outcome;
        $wager['version'] = $version;

        return Http::response(['success' => true, 'data' => ['wager' => $this->wagerResource($key), 'settlement' => $this->settlementResource($key, $version), 'replayed' => false]], 201);
    }

    private function reverse(Request $request, string $reference, array $body): PromiseInterface
    {
        if ($refusal = $this->gameKeyRefusal($request)) {
            return $refusal;
        }

        $key = "{$body['product']}|{$reference}";
        $version = (int) $body['version'];

        if (! isset($this->wagers[$key])) {
            return Http::response(['success' => false, 'code' => 'wager_not_found'], 404);
        }

        $wager = &$this->wagers[$key];

        if ($wager['tombstone']) {
            return Http::response(['success' => false, 'code' => 'wager_voided'], 409);
        }

        if ($wager['status'] === 'open') {
            return $wager['version'] === $version && ($wager['settlements'][$version]['status'] ?? null) === 'reversed'
                ? Http::response(['success' => true, 'data' => ['wager' => $this->wagerResource($key), 'settlement' => $this->settlementResource($key, $version), 'replayed' => true]])
                : Http::response(['success' => false, 'code' => 'not_settled'], 409);
        }

        if ($wager['version'] !== $version) {
            return Http::response(['success' => false, 'code' => 'version_out_of_order', 'status' => $wager['status'], 'settlement_version' => $wager['version']], 409);
        }

        $this->move($wager['customer'], -$wager['settlements'][$version]['credited']);
        $wager['settlements'][$version]['status'] = 'reversed';
        $wager['status'] = 'open';

        return Http::response(['success' => true, 'data' => ['wager' => $this->wagerResource($key), 'settlement' => $this->settlementResource($key, $version), 'replayed' => false]], 201);
    }

    private function lookup(Request $request, string $reference, array $query): PromiseInterface
    {
        if ($refusal = $this->gameKeyRefusal($request)) {
            return $refusal;
        }

        $key = "{$query['product']}|{$reference}";

        if (! isset($this->wagers[$key])) {
            return Http::response(['success' => false, 'code' => 'wager_not_found'], 404);
        }

        return Http::response(['success' => true, 'data' => [
            'wager' => $this->wagerResource($key),
            'settlements' => array_values(array_map(fn (int $version) => $this->settlementResource($key, $version), array_keys($this->wagers[$key]['settlements']))),
        ]]);
    }

    private function move(?int $customer, int $cents): void
    {
        if ($customer === null || $cents === 0) {
            return;
        }

        $this->balances[$customer] += $cents;
        $this->versions[$customer]++;
    }

    /** @return array{balance: float, locked_bonus: float, wallet_version: int} */
    private function balanceBlock(int $customer): array
    {
        return ['balance' => $this->balances[$customer] / 100, 'locked_bonus' => 0.0, 'wallet_version' => $this->versions[$customer]];
    }

    /** @return array<string, mixed> */
    private function wagerResource(string $key): array
    {
        $wager = $this->wagers[$key];

        return [
            'reference' => $wager['reference'], 'product' => $wager['product'], 'funding' => $wager['funding'],
            'stake' => $wager['stake'] / 100, 'free_bet_amount' => $wager['free_bet_amount'] / 100,
            'status' => $wager['status'], 'settlement_version' => $wager['version'],
        ];
    }

    /** @return array<string, mixed> */
    private function settlementResource(string $key, int $version): array
    {
        $settlement = $this->wagers[$key]['settlements'][$version];

        return [
            'version' => $version, 'outcome' => $settlement['outcome'], 'payout' => $settlement['payout'] / 100,
            'bonus' => $settlement['bonus'] / 100, 'tax' => $settlement['tax'] / 100, 'status' => $settlement['status'],
        ];
    }

    private function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
