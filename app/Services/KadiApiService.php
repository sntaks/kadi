<?php

namespace App\Services;

use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\PlayedGame;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KadiApiService
{
    protected PendingRequest $http;

    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.kadi_api.url');
        $this->http = Http::withHeaders([
            'x-api-key' => config('services.kadi_api.key'),
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    /**
     * Clone the shared client and attach an Idempotency-Key header.
     *
     * withHeaders() mutates and returns the same PendingRequest instance, so
     * calling it directly on $this->http would leak every prior key onto
     * subsequent requests. Cloning first keeps $this->http untouched.
     */
    protected function withIdempotencyKey(?string $idempotencyKey = null): PendingRequest
    {
        return (clone $this->http)->withHeaders([
            'Idempotency-Key' => $idempotencyKey ?? Str::uuid()->toString(),
        ]);
    }

    /**
     * Make a GET request
     *
     * @throws RequestException|ConnectionException
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->http->get($endpoint, $query)
            ->throw()
            ->json('data') ?? [];
    }

    /**
     * The game catalogue (`GET /games`): active games in lobby order, each with its products.
     *
     * @return list<array<string, mixed>>
     *
     * @throws RequestException|ConnectionException|\UnexpectedValueException
     */
    public function games(int $timeoutSeconds = 5): array
    {
        $data = (clone $this->http)->timeout($timeoutSeconds)->get('games')->throw()->json('data');

        // A 200 that is not a list of games (an object, an empty list, a proxy page) must never
        // replace a good catalogue, so it counts as a failure.
        $games = is_array($data) && array_is_list($data)
            ? array_values(array_filter($data, fn ($game) => is_array($game)
                && is_string($game['code'] ?? null)
                && is_string($game['launch_url'] ?? null)))
            : [];

        if ($games === []) {
            throw new \UnexpectedValueException('The games catalogue response has no games.');
        }

        return $games;
    }

    /**
     * Make a POST request
     *
     * @throws RequestException|ConnectionException
     */
    public function post(string $endpoint, array $data = [], string $bodyType = 'json', ?string $idempotencyKey = null): array
    {
        $request = $this->withIdempotencyKey($idempotencyKey);

        $response = $bodyType === 'form'
            ? $request->asForm()->post($endpoint, $data)
            : $request->post($endpoint, $data);

        return $response->throw()->json('data') ?? [];
    }

    /**
     * Make a PUT request
     *
     * @throws RequestException|ConnectionException
     */
    public function put(string $endpoint, array $data = [], string $bodyType = 'json', ?string $idempotencyKey = null): array
    {
        $request = $this->withIdempotencyKey($idempotencyKey);

        $response = $bodyType === 'form'
            ? $request->asForm()->put($endpoint, $data)
            : $request->put($endpoint, $data);

        return $response->throw()->json('data') ?? [];
    }

    /**
     * Get player statistics for a given date, cached for 1 hour.
     *
     * @throws RequestException|ConnectionException
     */
    public function getPlayerStats(int $linkedId, string $date): array
    {
        $cacheKey = "kadiapi_stats_{$linkedId}_{$date}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($linkedId, $date) {
            return $this->post('stats/customers/played', [
                'customer_id' => $linkedId,
                'start_date' => $date.' 00:00:00',
                'end_date' => $date.' 23:59:59',
            ]);
        });
    }

    /**
     * Register a new customer in KadiApi.
     *
     * @throws RequestException|ConnectionException
     */
    public function createCustomer(array $data): array
    {
        // Deterministic key: retries for the same customer reuse the same key
        // (so KadiApi returns the original result instead of 409), while
        // different customers always get distinct keys.
        $key = 'customer-create-'.($data['account_no'] ?? $data['google_id'] ?? Str::uuid()->toString());
        $data = $this->withNormalizedPhone($data);

        return $this->withIdempotencyKey($key)
            ->post('customers', $data)
            ->throw()
            ->json() ?? [];
    }

    /**
     * KadiApi must only ever receive 254XXXXXXXXX (no `+`). A blank phone_no is dropped rather than
     * sent, so it can never overwrite a stored number.
     */
    private function withNormalizedPhone(array $data): array
    {
        if (array_key_exists('phone_no', $data)) {
            $phone = PhoneNumber::normalize($data['phone_no'] === null ? null : (string) $data['phone_no']);

            if ($phone === null) {
                unset($data['phone_no']);
            } else {
                $data['phone_no'] = $phone;
            }
        }

        return $data;
    }

    /**
     * Fetch transactions for a customer, optionally filtered by type.
     *
     * @throws RequestException|ConnectionException
     */
    public function getTransactions(int $customerId, string $type = 'all', ?string $idempotencyKey = null): array
    {
        return $this->withIdempotencyKey($idempotencyKey)
            ->post('customers/transactions/'.encryptOpenSSL($customerId), [
                'payment_type' => $type,
            ])->throw()->json() ?? [];
    }

    /**
     * Update a customer profile in KadiApi.
     *
     * @throws RequestException|ConnectionException
     */
    public function updateCustomer(int $customerId, array $data, ?string $idempotencyKey = null): array
    {
        $data = $this->withNormalizedPhone($data);

        return $this->withIdempotencyKey($idempotencyKey)
            ->put('customers/'.encryptOpenSSL($customerId), $data)
            ->throw()
            ->json() ?? [];
    }

    /**
     * Fetch a customer profile by ID.
     *
     * @throws RequestException|ConnectionException
     */
    public function getCustomer(int $customerId): array
    {
        return $this->http->get('customers/'.encryptOpenSSL($customerId))
            ->throw()
            ->json() ?? [];
    }

    /**
     * Upload a profile picture for a customer.
     * Stores the image at kadi/images/{accounts_id}/{filename} on the API server.
     *
     * @throws RequestException|ConnectionException
     */
    public function uploadProfilePic(int $customerId, string $filePath, string $filename, ?string $idempotencyKey = null): array
    {
        return $this->withIdempotencyKey($idempotencyKey)
            ->attach('pic', fopen($filePath, 'r'), $filename)
            ->post('customers/'.encryptOpenSSL($customerId).'/pic')
            ->throw()
            ->json() ?? [];
    }

    /**
     * Make a DELETE request
     *
     * @throws RequestException|ConnectionException
     */
    public function delete(string $endpoint, ?string $idempotencyKey = null): array
    {
        return $this->withIdempotencyKey($idempotencyKey)
            ->delete($endpoint)
            ->throw()
            ->json('data') ?? [];
    }

    public function stkDeposit(User $user, int $amount, ?string $idempotencyKey = null): bool
    {
        try {
            $response = $this->withIdempotencyKey($idempotencyKey)
                ->post('deposits/'.encryptOpenSSL($user->linked_id), [
                    'amount' => (string) $amount,
                ])
                ->throw()
                ->json() ?? [];

            Log::info("StkPush Deposit Response for user {$user->id}: {$response['status']}");

            return $response['status'] === 'success';
        } catch (\Throwable $e) {
            Log::error("StkPush Deposit Error for user {$user->id}: {$e->getMessage()}");

            return false;
        }
    }

    public function stkLoad(User $user, array $options, ?string $idempotencyKey = null): bool
    {
        try {
            $response = $this->withIdempotencyKey($idempotencyKey)
                ->post('load/'.encryptOpenSSL($user->linked_id), [
                    'amount' => (string) $options['price'],
                    'type' => $options['type'],
                ])
                ->throw()
                ->json() ?? [];

            Log::info("StkPush Deposit Response for user {$user->id}: {$response['status']}");

            return $response['status'] === 'success';
        } catch (\Throwable $e) {
            Log::error("StkPush Load Error for user {$user->id}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * A player's latest single games, tournaments and jackpots, newest first, normalised by
     * App\Support\PlayedGame and keyed game | tournament | jackpot.
     *
     * Endpoint : GET {kadi.game_disputes.played_endpoint}/{encrypted_customer_id}
     *
     * @return array<string, list<array<string, mixed>>>
     *
     * @throws RequestException|ConnectionException
     */
    public function getPlayedGames(int $customerId): array
    {
        $body = $this->http->get(config('kadi.game_disputes.played_endpoint').'/'.encryptOpenSSL($customerId))
            ->throw()
            ->json() ?? [];

        return PlayedGame::listsFromApi(is_array($body) ? $body : [], (int) config('kadi.game_disputes.games_per_list', 10));
    }

    /**
     * File a complaint about a played game or round, which puts the disputed winnings in escrow.
     *
     * Endpoint : POST complaints   (30/min for the whole API key, idempotent)
     * Payload  : customer_id (plain id), exactly one of game_wallet_id | competition_wallet_id,
     *            transaction_ids? (open opponent wallet only), reason (3-255), description? (<=2000)
     * Success  : 201 {success: true, data: {...complaint}}
     * Errors   : 409 already under an open complaint (or Idempotency-Key reused with another body),
     *            422 {errors} validation or {success: false, message} cannot be filed, 429 rate limit.
     *
     * Pass the same $idempotencyKey when retrying the same complaint, so a retry after a timeout
     * returns the original complaint instead of filing a second one.
     */
    public function fileComplaint(array $payload, string $idempotencyKey): ComplaintResult
    {
        try {
            $response = $this->withIdempotencyKey($idempotencyKey)->post('complaints', $payload);
        } catch (ConnectionException $e) {
            Log::warning('Complaint connection error for customer '.($payload['customer_id'] ?? '?').': '.$e->getMessage());

            return ComplaintResult::unknown();
        } catch (\Throwable $e) {
            Log::error('Complaint error for customer '.($payload['customer_id'] ?? '?').': '.$e->getMessage());

            return ComplaintResult::unknown();
        }

        $status = $response->status();
        $body = $response->json() ?? [];
        $body = is_array($body) ? $body : [];

        Log::info('Complaint response for customer '.($payload['customer_id'] ?? '?').": HTTP {$status}");

        if ($response->successful() && ($body['success'] ?? false) === true && is_array($body['data'] ?? null)) {
            return ComplaintResult::filed($body['data']);
        }

        $apiMessage = is_string($body['message'] ?? null) ? trim(strip_tags($body['message'])) : '';
        $apiMessage = mb_strlen($apiMessage) <= 255 ? $apiMessage : '';

        return match (true) {
            // Our keys hash the whole body, so a reused key with another body means a bug: never show it as "reported".
            $status === 409 && str_contains($apiMessage, 'Idempotency-Key') => ComplaintResult::rejected('Something changed while sending. Please submit your report again.'),
            $status === 409 => ComplaintResult::alreadyReported(),
            $status === 422 && is_array($body['errors'] ?? null) => ComplaintResult::rejected('Please check the highlighted fields.', self::firstErrors($body['errors'])),
            $status === 422 => ComplaintResult::rejected($apiMessage !== '' ? $apiMessage : 'This cannot be reported.'),
            $status === 404 => ComplaintResult::rejected('We could not find your game account. Please contact support.'),
            $status === 429 => ComplaintResult::rejected('Too many reports are being sent right now. Please try again shortly.'),
            // A 2xx we cannot read, or a gateway error: the complaint may exist.
            $response->successful(), $status >= 500 => ComplaintResult::unknown(),
            default => ComplaintResult::rejected('Your report could not be sent right now. Please try again shortly.'),
        };
    }

    /**
     * The customer's complaints that are still open (`pending_dispute`), newest first.
     *
     * Endpoint : GET complaints?customer_id=&status=pending_dispute   (20/min, shared with stats)
     *
     * @return list<array<string, mixed>>
     *
     * @throws RequestException|ConnectionException
     */
    public function getOpenComplaints(int $customerId): array
    {
        $data = $this->http->get('complaints', [
            'customer_id' => $customerId,
            'status' => 'pending_dispute',
            'per_page' => 200,
        ])->throw()->json('data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * Laravel-style `errors` ({field: [messages]}) to field => first message.
     *
     * @return array<string, string>
     */
    private static function firstErrors(array $errors): array
    {
        $first = [];

        foreach ($errors as $field => $messages) {
            $message = is_array($messages) ? ($messages[0] ?? null) : $messages;

            if (is_string($field) && is_string($message)) {
                $first[$field] = trim(strip_tags($message));
            }
        }

        return $first;
    }

    /**
     * Request an M-Pesa withdrawal (B2C payout from the wallet to the
     * customer's registered phone).
     *
     * Endpoint : POST withdraw/{encrypted_linked_id}   (5/min, idempotent)
     * Payload  : ['amount' => numeric, min 1]
     * Success  : 201 {status: "success", ledger_entry_id}
     * Errors   : 400 balance/phone, 404 customer/wallet, 422 validation,
     *            429 rate limit, 500 B2C failed (debit reversed by the API).
     *
     * Pass the same $idempotencyKey on a retry of the same withdrawal so the
     * API replays the original result instead of paying out twice. A timeout
     * or connection error is reported as WithdrawResult::UNKNOWN because the
     * wallet may already have been debited.
     */
    public function withdraw(User $user, float $amount, ?string $idempotencyKey = null): WithdrawResult
    {
        try {
            $response = $this->withIdempotencyKey($idempotencyKey)
                ->post('withdraw/'.encryptOpenSSL($user->linked_id), [
                    'amount' => intval($amount),
                ]);
        } catch (ConnectionException $e) {
            Log::warning("Withdrawal connection error for user {$user->id}: {$e->getMessage()}");

            return WithdrawResult::unknown();
        } catch (\Throwable $e) {
            Log::error("Withdrawal error for user {$user->id}: {$e->getMessage()}");

            return WithdrawResult::unknown();
        }

        $status = $response->status();
        $body = $response->json() ?? [];
        $apiMessage = is_string($body['status'] ?? null) ? strtolower($body['status']) : '';

        Log::info("Withdrawal response for user {$user->id}: HTTP {$status}");

        if ($response->successful() && ($body['status'] ?? '') === 'success') {
            return WithdrawResult::success($body['ledger_entry_id'] ?? null);
        }

        if ($status === 400 && ($body['code'] ?? null) === 'signup_bonus_locked') {
            return WithdrawResult::rejected(self::signupBonusLockedMessage($body, 'withdraw'));
        }

        return match (true) {
            $status === 400 && str_contains($apiMessage, 'phone') => WithdrawResult::rejected('We could not find a phone number on your account. Please update it and try again.'),
            $status === 400 => WithdrawResult::rejected('Insufficient balance for this withdrawal.'),
            $status === 404 => WithdrawResult::rejected('We could not find your wallet. Please contact support.'),
            $status === 422 => WithdrawResult::rejected('That withdrawal amount is not valid.'),
            $status === 429 => WithdrawResult::rejected('Too many attempts. Please wait a minute and try again.'),
            $status === 500 => WithdrawResult::rejected('M-Pesa could not complete the withdrawal. Your balance was not charged. Please try again shortly.'),
            // Any other 5xx/gateway response: we cannot tell whether money moved.
            $status >= 500 => WithdrawResult::unknown(),
            default => WithdrawResult::rejected('Withdrawal could not be processed right now. Please try again shortly.'),
        };
    }

    /**
     * The player-facing message for KadiApi's `signup_bonus_locked` refusal (withdraw, transfer, coin
     * purchase): {"code": "signup_bonus_locked", "locked_amount": 20, "available": 5}. Check `code`,
     * never the message text.
     */
    public static function signupBonusLockedMessage(array $body, string $verb = 'withdraw'): string
    {
        $locked = number_format(max(0, (float) ($body['locked_amount'] ?? 0)));
        $available = number_format(max(0, floor((float) ($body['available'] ?? 0))));

        return "KES {$locked} of your signup bonus has to be played first. You can {$verb} up to KES {$available}.";
    }

    /*
    |--------------------------------------------------------------------------
    | Referrals (see docs/referrals.md)
    |--------------------------------------------------------------------------
    | KadiApi owns the referral ledger, bonuses and payouts. Never log codes typed by players,
    | referred players' names or phone numbers.
    */

    /**
     * The player's referral code, link and QR code, or null when they have none yet (404).
     *
     * Endpoint : GET customers/{enc}/referral-code
     *
     * @return array<string, mixed>|null
     *
     * @throws RequestException|ConnectionException
     */
    public function getReferralCode(int $customerId): ?array
    {
        $response = $this->http->get('customers/'.encryptOpenSSL((string) $customerId).'/referral-code');

        if ($response->status() === 404) {
            return null;
        }

        $data = $response->throw()->json('data');

        return is_array($data) ? $data : null;
    }

    /**
     * Set or change the player's code. The old code stops matching new sign-ups; earlier referrals stay.
     *
     * Endpoint : PUT customers/{enc}/referral-code   (30/min)
     * Payload  : code (4-20 letters/digits), link? (URL <= 2048), qr_code? (image URL or data URI <= 500,000)
     * Errors   : 409 code taken, 422 {errors}
     */
    public function putReferralCode(int $customerId, string $code, ?string $link = null, ?string $qrCode = null): ReferralCodeResult
    {
        try {
            $response = $this->http->put('customers/'.encryptOpenSSL((string) $customerId).'/referral-code', array_filter([
                'code' => $code,
                'link' => $link,
                'qr_code' => $qrCode,
            ], fn ($value) => $value !== null));
        } catch (\Throwable $e) {
            Log::warning("Referral code save for customer {$customerId}: ".class_basename($e));

            return ReferralCodeResult::error();
        }

        $status = $response->status();
        $data = $response->json('data');

        if ($response->successful() && is_array($data)) {
            return ReferralCodeResult::saved($data);
        }

        if ($status === 409) {
            return ReferralCodeResult::taken();
        }

        if ($status === 422) {
            $fields = is_array($response->json('errors')) ? implode(',', array_keys($response->json('errors'))) : '';
            Log::error("Referral code refused for customer {$customerId}: HTTP 422 {$fields}");

            return ReferralCodeResult::invalid();
        }

        Log::warning("Referral code save for customer {$customerId}: HTTP {$status}");

        return ReferralCodeResult::error();
    }

    /**
     * Who owns a referral code, for "Invited by ..." on sign-up. Null for an unknown code (404, which
     * includes agent codes) and on any error: this must never block sign-up.
     *
     * Endpoint : GET referrals/lookup?code=
     *
     * @return array<string, mixed>|null
     */
    public function lookupReferralCode(string $code): ?array
    {
        try {
            $response = (clone $this->http)->timeout(5)->get('referrals/lookup', ['code' => $code]);
        } catch (\Throwable) {
            return null;
        }

        $data = $response->successful() ? $response->json('data') : null;

        return is_array($data) ? $data : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Verification and promotions (see docs/promotions.md)
    |--------------------------------------------------------------------------
    */

    /**
     * Report that a player verified their e-mail and phone, for every player. Records the
     * verification, pays any referral bonus now due and grants the signup bonus when the player
     * qualifies (`data.signup_bonus`, otherwise null). Safe to repeat: nothing is paid twice.
     * Replaces POST customers/{enc}/referral/verified; never call both.
     *
     * Endpoint : POST customers/{enc}/verified   (30/min for the whole site)
     *
     * @throws RequestException|ConnectionException
     */
    public function reportVerified(int $customerId): array
    {
        return $this->http->post('customers/'.encryptOpenSSL((string) $customerId).'/verified')
            ->throw()
            ->json() ?? [];
    }

    /**
     * Whether a player could sign up with a promo code right now. ['valid' => false] on 404 (unknown,
     * expired, deactivated, used up, or the promotion is off); null on any other error, because then
     * we cannot tell. Never blocks sign-up.
     *
     * Endpoint : GET promo-codes/lookup?code=
     *
     * @return array{valid: bool, code?: string, expires_at?: ?string}|null
     */
    public function lookupPromoCode(string $code): ?array
    {
        try {
            $response = (clone $this->http)->timeout(5)->get('promo-codes/lookup', ['code' => $code]);
        } catch (\Throwable) {
            return null;
        }

        if ($response->status() === 404) {
            return ['valid' => false];
        }

        $data = $response->successful() ? $response->json('data') : null;

        if (! is_array($data)) {
            return null;
        }

        return [
            'valid' => true,
            'code' => (string) ($data['code'] ?? $code),
            'expires_at' => is_string($data['expires_at'] ?? null) ? $data['expires_at'] : null,
        ];
    }

    /**
     * The player's bonuses and how much is still locked (cannot be withdrawn yet).
     *
     * Endpoint : GET customers/{enc}/promotions
     *
     * @return array{locked_amount: float, items: list<array<string, mixed>>}
     *
     * @throws RequestException|ConnectionException
     */
    public function getPromotions(int $customerId): array
    {
        $data = $this->http->get('customers/'.encryptOpenSSL((string) $customerId).'/promotions')
            ->throw()
            ->json('data') ?? [];

        return [
            'locked_amount' => max(0.0, (float) ($data['locked_amount'] ?? 0)),
            'items' => array_values(array_filter((array) ($data['items'] ?? []), 'is_array')),
        ];
    }

    /**
     * Endpoint : GET customers/{enc}/referrals/stats
     *
     * @throws RequestException|ConnectionException
     */
    public function getReferralStats(int $customerId): array
    {
        return $this->http->get('customers/'.encryptOpenSSL((string) $customerId).'/referrals/stats')
            ->throw()
            ->json('data') ?? [];
    }

    /**
     * The players this player referred, newest first (Laravel paginated: data, links, meta).
     * Phone numbers arrive masked.
     *
     * Endpoint : GET customers/{enc}/referrals?page=&per_page=&status=
     *
     * @throws RequestException|ConnectionException
     */
    public function getReferrals(int $customerId, int $page = 1, ?string $status = null, int $perPage = 20): array
    {
        return $this->http->get('customers/'.encryptOpenSSL((string) $customerId).'/referrals', array_filter([
            'page' => $page,
            'per_page' => $perPage,
            'status' => $status,
        ]))->throw()->json() ?? [];
    }

    /**
     * Referral wallet: data.{balance, total_earned, withdrawable, minimum_withdrawal, bonuses[]} and
     * the bonuses' pagination.
     *
     * Endpoint : GET customers/{enc}/referral-wallet?page=&per_page=
     *
     * @throws RequestException|ConnectionException
     */
    public function getReferralWallet(int $customerId, int $page = 1, int $perPage = 10): array
    {
        return $this->http->get('customers/'.encryptOpenSSL((string) $customerId).'/referral-wallet', [
            'page' => $page,
            'per_page' => $perPage,
        ])->throw()->json() ?? [];
    }

    /**
     * Endpoint : GET customers/{enc}/referral-wallet/withdrawals?page=&per_page=
     *
     * @throws RequestException|ConnectionException
     */
    public function getReferralWithdrawals(int $customerId, int $page = 1, int $perPage = 10): array
    {
        return $this->http->get('customers/'.encryptOpenSSL((string) $customerId).'/referral-wallet/withdrawals', [
            'page' => $page,
            'per_page' => $perPage,
        ])->throw()->json() ?? [];
    }

    /**
     * Withdraw referral earnings to the player's M-Pesa number (stored in KadiApi). KadiApi pays it
     * from the referral shortcode; this site never sends an M-Pesa request for it.
     *
     * Endpoint : POST customers/{enc}/referral-wallet/withdraw   (5/min, idempotent)
     * Payload  : amount (whole shillings, >= minimum_withdrawal, <= balance)
     * Success  : 201 data.status "processing"; 202 data.status "pending" (outcome unknown)
     * Errors   : 400 balance/phone, 403 switched off, 422 below minimum, 502 rejected and restored,
     *            503 not configured.
     *
     * Use one $idempotencyKey per attempt, and the same one when retrying an attempt whose outcome
     * is unknown, so a retry replays instead of paying twice.
     */
    public function withdrawReferral(User $user, int $amount, string $idempotencyKey): ReferralWithdrawResult
    {
        try {
            $response = $this->withIdempotencyKey($idempotencyKey)
                ->post('customers/'.encryptOpenSSL((string) $user->linked_id).'/referral-wallet/withdraw', [
                    'amount' => $amount,
                ]);
        } catch (\Throwable $e) {
            Log::warning("Referral withdrawal for user {$user->id}: ".class_basename($e));

            return ReferralWithdrawResult::pending();
        }

        $status = $response->status();
        $message = is_string($response->json('message')) ? strtolower($response->json('message')) : '';

        Log::info("Referral withdrawal response for user {$user->id}: HTTP {$status}");

        return match (true) {
            $status === 201 => ReferralWithdrawResult::processing(),
            $status === 202 => ReferralWithdrawResult::pending(),
            $status === 502 => ReferralWithdrawResult::restored(),
            $status === 400 && str_contains($message, 'phone') => ReferralWithdrawResult::rejected('We could not find a valid M-Pesa number on your account. Please contact support.'),
            $status === 400 => ReferralWithdrawResult::rejected('Insufficient referral balance for this withdrawal.'),
            $status === 403 => ReferralWithdrawResult::rejected('Referral withdrawals are paused right now. Please try again later.'),
            $status === 422 => ReferralWithdrawResult::rejected('The minimum referral withdrawal is KES '.config('kadi.referrals.minimum_withdrawal').'.'),
            $status === 429 => ReferralWithdrawResult::rejected('Too many attempts. Please wait a minute and try again.'),
            $status === 503 => ReferralWithdrawResult::rejected('Referral withdrawals are temporarily unavailable. Please try again later.'),
            // Any other 2xx or 5xx: we cannot tell whether money moved.
            $response->successful(), $status >= 500 => ReferralWithdrawResult::pending(),
            default => ReferralWithdrawResult::rejected('Your withdrawal could not be processed right now. Please try again shortly.'),
        };
    }
}
