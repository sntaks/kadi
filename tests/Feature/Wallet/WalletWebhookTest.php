<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

// webhookUrl()/webhookPayload()/sign()/callWebhook()/webhookUser()/WEBHOOK_SECRET: tests/Support/wallet-webhook.php.

beforeEach(function () {
    config(['kadi.wallet_webhook.secrets' => [WEBHOOK_SECRET]]);
});

// --- Signature and replay verification --------------------------------------

test('a valid signature is accepted and applies the balance', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 777.25, 'balance_version' => 5]);

    callWebhook($payload, sign($payload))->assertOk()->assertJson(['status' => 'ok']);

    expect(Cache::get("wallet_balance_{$user->id}"))->toEqual(777.25)
        ->and(Cache::get("kadi.customer.{$user->id}")['balance'])->toEqual(777.25)
        ->and(Cache::get("wallet_balance_version_{$user->id}"))->toEqual(5);
});

test('accepting either the old or the new secret during rotation', function () {
    $old = 'old-secret-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    $new = 'new-secret-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    config(['kadi.wallet_webhook.secrets' => [$old, $new]]);

    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);

    callWebhook($payload, sign($payload, $old))->assertOk();

    $payload2 = webhookPayload(['customer_id' => $user->linked_id, 'balance_version' => 2]);
    callWebhook($payload2, sign($payload2, $new))->assertOk();
});

test('a wrong secret is rejected and applies nothing', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);

    callWebhook($payload, sign($payload, 'wrong-secret-xxxxxxxxxxxxxxxxxxxxxxxxx'))->assertStatus(401);

    expect(Cache::get("wallet_balance_{$user->id}"))->toBeNull();
});

test('a missing signature header is rejected', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);
    $headers = sign($payload);
    unset($headers['X-Kadi-Signature']);

    callWebhook($payload, $headers)->assertStatus(401);
});

test('no secrets configured disables the endpoint for everyone', function () {
    config(['kadi.wallet_webhook.secrets' => []]);

    $payload = webhookPayload();
    callWebhook($payload, sign($payload))->assertStatus(503);
});

test('a stale timestamp is rejected', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);

    callWebhook($payload, sign($payload, timestamp: time() - 301))->assertStatus(401);
});

test('a timestamp too far in the future is rejected', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);

    callWebhook($payload, sign($payload, timestamp: time() + 301))->assertStatus(401);
});

test('signature and timestamp failures return the same generic response', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);

    $badSignature = callWebhook($payload, sign($payload, 'wrong-secret-xxxxxxxxxxxxxxxxxxxxxxxxx'));
    $badTimestamp = callWebhook($payload, sign($payload, timestamp: time() - 301));

    expect($badSignature->status())->toBe($badTimestamp->status())
        ->and($badSignature->json())->toEqual($badTimestamp->json());
});

// --- Deduplication and ordering ---------------------------------------------

test('a duplicate event_id is a no-op on the second delivery', function () {
    $user = webhookUser();
    $eventId = (string) Str::uuid();

    $first = webhookPayload(['customer_id' => $user->linked_id, 'event_id' => $eventId, 'balance' => 100.0, 'balance_version' => 1]);
    callWebhook($first, sign($first))->assertOk();

    // Identical retry in KadiApi's contract carries the same event_id and body; simulate a buggy
    // resend with a different balance to prove the no-op is keyed on event_id alone.
    $retry = webhookPayload(['customer_id' => $user->linked_id, 'event_id' => $eventId, 'balance' => 999.0, 'balance_version' => 2]);
    callWebhook($retry, sign($retry))->assertOk();

    expect(Cache::get("wallet_balance_{$user->id}"))->toEqual(100.0);
});

test('an older balance_version than what is already applied is ignored', function () {
    $user = webhookUser();

    $newer = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 500.0, 'balance_version' => 10]);
    callWebhook($newer, sign($newer))->assertOk();

    $older = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 1.0, 'balance_version' => 3]);
    callWebhook($older, sign($older))->assertOk();

    expect(Cache::get("wallet_balance_{$user->id}"))->toEqual(500.0)
        ->and(Cache::get("wallet_balance_version_{$user->id}"))->toEqual(10);
});

test('an equal balance_version is also ignored', function () {
    $user = webhookUser();

    $first = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 500.0, 'balance_version' => 10]);
    callWebhook($first, sign($first))->assertOk();

    $same = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 42.0, 'balance_version' => 10]);
    callWebhook($same, sign($same))->assertOk();

    expect(Cache::get("wallet_balance_{$user->id}"))->toEqual(500.0);
});

test('a strictly newer balance_version is applied', function () {
    $user = webhookUser();

    $first = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 500.0, 'balance_version' => 10]);
    callWebhook($first, sign($first))->assertOk();

    $newer = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 600.0, 'balance_version' => 11]);
    callWebhook($newer, sign($newer))->assertOk();

    expect(Cache::get("wallet_balance_{$user->id}"))->toEqual(600.0)
        ->and(Cache::get("wallet_balance_version_{$user->id}"))->toEqual(11);
});

test('the kadi.customer cache is merged, not overwritten', function () {
    $user = webhookUser();
    Cache::put("kadi.customer.{$user->id}", ['balance' => 1.0, 'google_id' => 'abc123'], now()->addHour());

    $payload = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 42.0]);
    callWebhook($payload, sign($payload))->assertOk();

    expect(Cache::get("kadi.customer.{$user->id}"))->toEqual(['balance' => 42.0, 'google_id' => 'abc123']);
});

// --- Customer mapping --------------------------------------------------------

test('an unmapped customer_id is acknowledged without applying anything', function () {
    $payload = webhookPayload(['customer_id' => 999999]);

    callWebhook($payload, sign($payload))->assertOk()->assertJson(['status' => 'ok']);
});

test('a customer_id matching more than one user is acknowledged without guessing', function () {
    User::factory()->create(['linked_id' => 6161]);
    User::factory()->create(['linked_id' => 6161]);

    $payload = webhookPayload(['customer_id' => 6161]);

    callWebhook($payload, sign($payload))->assertOk()->assertJson(['status' => 'ok']);
});

// --- Payload validation -------------------------------------------------------

test('a malformed payload is rejected with 422 and never reaches the cache', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);
    unset($payload['balance']);

    callWebhook($payload, sign($payload))->assertStatus(422);

    expect(Cache::get("wallet_balance_{$user->id}"))->toBeNull();
});

test('an unrecognised event name is rejected with 422', function () {
    $payload = webhookPayload(['event' => 'wallet.something-else']);

    callWebhook($payload, sign($payload))->assertStatus(422);
});

test('a non-uuid event_id is rejected with 422', function () {
    $payload = webhookPayload(['event_id' => 'not-a-uuid']);

    callWebhook($payload, sign($payload))->assertStatus(422);
});

// --- Transport ----------------------------------------------------------------

test('the route requires no session, auth guard or csrf token', function () {
    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);

    // A plain unauthenticated JSON POST, exactly as KadiApi would send it.
    test()->postJson(webhookUrl(), $payload, sign($payload))->assertOk();
});
