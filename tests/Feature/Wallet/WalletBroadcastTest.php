<?php

use App\Events\WalletBalanceUpdated;
use App\Livewire\Wallet\Index as WalletIndex;
use App\Livewire\WalletBalance;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Features\SupportEvents\SupportEvents;

// Reuses webhookUser()/webhookPayload()/sign()/callWebhook()/WEBHOOK_SECRET from WalletWebhookTest.php
// (global helpers, same webhook endpoint) — its own beforeEach() is file-scoped, so this file needs
// its own copy to get a configured secret.
beforeEach(function () {
    config(['kadi.wallet_webhook.secrets' => [WEBHOOK_SECRET]]);
});

test('a valid webhook call broadcasts the new balance on the user private channel', function () {
    Event::fake([WalletBalanceUpdated::class]);

    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id, 'balance' => 321.5, 'balance_version' => 2]);

    callWebhook($payload, sign($payload))->assertOk();

    Event::assertDispatched(WalletBalanceUpdated::class, function (WalletBalanceUpdated $event) use ($user) {
        return $event->userId === $user->id
            && $event->balance === 321.5
            && $event->broadcastAs() === 'wallet.updated'
            && $event->broadcastWith() === ['balance' => 321.5]
            && count($event->broadcastOn()) === 1
            && $event->broadcastOn()[0] instanceof PrivateChannel
            && $event->broadcastOn()[0]->name === "private-user.{$user->id}";
    });
});

test('a duplicate event_id does not broadcast again', function () {
    Event::fake([WalletBalanceUpdated::class]);

    $user = webhookUser();
    $eventId = (string) Str::uuid();
    $payload = webhookPayload(['customer_id' => $user->linked_id, 'event_id' => $eventId]);

    callWebhook($payload, sign($payload))->assertOk();
    callWebhook($payload, sign($payload))->assertOk();

    Event::assertDispatchedTimes(WalletBalanceUpdated::class, 1);
});

test('an unmapped customer_id does not broadcast', function () {
    Event::fake([WalletBalanceUpdated::class]);

    $payload = webhookPayload(['customer_id' => 999999]);

    callWebhook($payload, sign($payload))->assertOk();

    Event::assertNotDispatched(WalletBalanceUpdated::class);
});

test('a stale balance_version does not broadcast', function () {
    Event::fake([WalletBalanceUpdated::class]);

    $user = webhookUser();

    $newer = webhookPayload(['customer_id' => $user->linked_id, 'balance_version' => 10]);
    callWebhook($newer, sign($newer))->assertOk();

    $older = webhookPayload(['customer_id' => $user->linked_id, 'balance_version' => 3]);
    callWebhook($older, sign($older))->assertOk();

    Event::assertDispatchedTimes(WalletBalanceUpdated::class, 1);
});

test('the webhook still returns 200 even when broadcasting throws', function () {
    Event::listen(WalletBalanceUpdated::class, function () {
        throw new RuntimeException('reverb is down');
    });

    $user = webhookUser();
    $payload = webhookPayload(['customer_id' => $user->linked_id]);

    callWebhook($payload, sign($payload))->assertOk()->assertJson(['status' => 'ok']);
});

// --- routes/channels.php authorization (real /broadcasting/auth endpoint, no Reverb server needed:
// Pusher-protocol channel auth is a local HMAC signature, not a call to the socket server). The test
// suite forces BROADCAST_CONNECTION=null (phpunit.xml) so other tests never hit a real broadcaster;
// these three opt into the real 'reverb' driver. routes/channels.php was already require()'d once
// during boot against the 'null' driver, so switching the default here needs a second require to
// register the channel on the freshly-resolved 'reverb' driver instance too.
function useReverbBroadcaster(): void
{
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
}

test('an authenticated user can authorize their own private wallet channel', function () {
    useReverbBroadcaster();
    $user = User::factory()->create();

    test()->actingAs($user)->postJson('/broadcasting/auth', [
        'channel_name' => "private-user.{$user->id}",
        'socket_id' => '1234.5678',
    ])->assertOk();
});

test('an authenticated user cannot authorize another user\'s private wallet channel', function () {
    useReverbBroadcaster();
    $user = User::factory()->create();
    $other = User::factory()->create();

    test()->actingAs($user)->postJson('/broadcasting/auth', [
        'channel_name' => "private-user.{$other->id}",
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});

test('a guest cannot authorize any private wallet channel', function () {
    useReverbBroadcaster();
    $user = User::factory()->create();

    test()->postJson('/broadcasting/auth', [
        'channel_name' => "private-user.{$user->id}",
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});

// --- Livewire wiring: the exact "echo-private:{channel},{event}" string Livewire's JS parses to
// call window.Echo.private(channel).listen(event, ...). Placeholders resolve from a plain public
// property (verified by reading vendor/livewire/livewire/dist/livewire.js), not "{this.prop}" —
// this test would catch a typo or a renamed property silently breaking the real-time path.

test('WalletBalance registers the echo-private wallet.updated listener for the signed-in user', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(WalletBalance::class);

    $listeners = SupportEvents::getComponentListeners($component->instance());

    expect(array_keys($listeners))->toContain("echo-private:user.{$user->id},.wallet.updated");
});

test('Wallet\Index registers the echo-private wallet.updated listener for the signed-in user', function () {
    $user = User::factory()->create(['linked_id' => 6161]);

    $component = Livewire::actingAs($user)->test(WalletIndex::class);

    $listeners = SupportEvents::getComponentListeners($component->instance());

    expect(array_keys($listeners))->toContain("echo-private:user.{$user->id},.wallet.updated");
});

test('a wallet.updated broadcast clears an "unavailable" header balance', function () {
    $user = User::factory()->create(['linked_id' => 7373]);

    $component = Livewire::actingAs($user)->test(WalletBalance::class)
        ->set('hasError', true);

    Cache::put("wallet_balance_{$user->id}", 150.0, now()->addSeconds(WalletBalance::BALANCE_TTL_SECONDS));

    $component->dispatch("echo-private:user.{$user->id},.wallet.updated")
        ->assertSet('hasError', false)
        ->assertSet('balance', 150.0);
});

test('Wallet\Index syncCustomer treats a profile without a balance as 0', function () {
    $user = User::factory()->create(['linked_id' => 7474]);
    Cache::put("kadi.customer.{$user->id}", ['id' => 7474, 'balance' => 80], now()->addHour());

    $component = Livewire::actingAs($user)->test(WalletIndex::class)
        ->assertSet('balance', 80.0);

    Cache::put("kadi.customer.{$user->id}", ['id' => 7474], now()->addHour());

    $component->dispatch("echo-private:user.{$user->id},.wallet.updated")
        ->assertSet('balance', 0.0);
});
