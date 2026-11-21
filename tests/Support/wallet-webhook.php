<?php

/*
 * Wallet webhook helpers shared by WalletWebhookTest and WalletBroadcastTest. Loaded from tests/Pest.php, so every
 * parallel worker has them whichever test file it runs first.
 */

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const WEBHOOK_SECRET = 'test-secret-at-least-32-bytes-long-xxxx';

function webhookUrl(): string
{
    return route('api.v1.wallet-webhooks.store');
}

function webhookPayload(array $overrides = []): array
{
    return array_replace([
        'event' => 'wallet.updated',
        'event_id' => (string) Str::uuid(),
        'customer_id' => 6161,
        'balance' => 1250.50,
        'balance_version' => 1,
        'reason' => 'deposit',
        'occurred_at' => now()->toIso8601String(),
    ], $overrides);
}

function sign(array $payload, ?string $secret = WEBHOOK_SECRET, ?int $timestamp = null): array
{
    $timestamp ??= time();
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = $secret === null ? '' : hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return [
        'X-Kadi-Event-Id' => $payload['event_id'],
        'X-Kadi-Timestamp' => (string) $timestamp,
        'X-Kadi-Signature' => $signature,
    ];
}

function callWebhook(array $payload, array $headers): TestResponse
{
    return test()->postJson(webhookUrl(), $payload, $headers);
}

function webhookUser(int $customerId = 6161): User
{
    return User::factory()->create(['linked_id' => $customerId]);
}
