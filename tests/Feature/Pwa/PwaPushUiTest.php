<?php

use App\Models\User;

function pwaUserMeta(string $html): ?string
{
    return preg_match('/<meta name="pwa-user" content="([^"]*)">/', $html, $m) ? $m[1] : null;
}

test('the account page has a notifications tab with the push switch', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('account'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-test="account-tab-notifications"')
        ->toContain("tab === 'notifications'")
        ->toContain('data-test="pwa-notifications"')
        ->toContain('data-test="pwa-notifications-switch"')
        ->toContain('role="switch"')
        // one control per unhappy state, so nobody is left with a silent failure
        ->toContain('data-test="pwa-notifications-unsupported"')
        ->toContain('data-test="pwa-notifications-ios"')
        ->toContain('data-test="pwa-notifications-denied"')
        ->toContain('data-test="pwa-notifications-error"')
        ->toContain('role="alert"');
});

test('the notifications panel opts out of livewire morphing', function () {
    $html = view('components.pwa.notification-toggle')->render();

    expect($html)->toContain('wire:ignore');
});

test('the toggle never dereferences the nullable error without a guard', function () {
    $html = file_get_contents(resource_path('views/components/pwa/notification-toggle.blade.php'));

    // Alpine evaluates bindings even inside hidden blocks; `error` is null until something fails.
    expect(preg_match('/\$store\.pwaPush\.error\.[a-z]/i', $html))->toBe(0);
});

test('the account page can open straight on the notifications tab', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('account'))->getContent();

    expect($html)->toContain("tabs: ['profile', 'security', 'notifications', 'connected']")
        ->toContain('tab = tabs.includes(h) ? h');
});

test('signed-in pages carry an opaque per-account marker and guests do not', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $marker = pwaUserMeta($this->actingAs($user)->get(route('account'))->getContent());
    $otherMarker = pwaUserMeta($this->actingAs($other)->get(route('account'))->getContent());

    expect($marker)->toMatch('/^[0-9a-f]{16}$/')
        ->and($marker)->not->toBe((string) $user->id) // never the database id
        ->and($otherMarker)->not->toBe($marker);

    // Stable for the same account across requests.
    expect(pwaUserMeta($this->actingAs($user)->get(route('wallet'))->getContent()))->toBe($marker);

    auth()->logout();
    $this->flushSession();

    expect(pwaUserMeta($this->get(route('login'))->getContent()))->toBeNull();
    expect($this->get(route('home'))->getContent())->not->toContain('name="pwa-user"');
});

test('the push module is loaded and keeps its safety behaviours', function () {
    $app = file_get_contents(resource_path('js/app.js'));
    $push = file_get_contents(resource_path('js/pwa/push.js'));

    expect($app)->toContain("from './pwa/push.js'")->toContain('initPush()');

    expect($push)
        // permission is only ever requested from the click handler, never on load
        ->toContain('Notification.requestPermission()')
        ->toContain('userVisibleOnly: true')
        ->toContain("'X-CSRF-TOKEN'")
        ->toContain("credentials: 'same-origin'")
        // rollback when the server refuses a new subscription
        ->toContain('if (createdHere && subscription) await subscription.unsubscribe()')
        // logout removes this device's server row and can never hang logout
        ->toContain('LOGOUT_TIMEOUT_MS')
        ->toContain("request('DELETE'")
        // shared device protection
        ->toContain('optedInAs !== me')
        // states from the plan
        ->toContain("'ios-needs-install'")
        ->toContain("'granted-subscribed'")
        ->toContain("'granted-unsubscribed'")
        ->toContain("'denied'")
        ->toContain("'unsupported'");

    // The test-send action posts to the server-side test route and explains every outcome.
    expect($push)->toContain("const TEST_URL = '/push/test'")
        ->toContain('async function sendTest()')
        ->toContain('describeTest(')
        ->toContain('sendTest,');

    // The request for permission must not be reachable from the load-time path.
    $detectState = substr($push, strpos($push, 'async function detectState()'), strpos($push, '// User actions') - strpos($push, 'async function detectState()'));
    expect($detectState)->not->toContain('requestPermission');
});
