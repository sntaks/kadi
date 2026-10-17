<?php

use App\Livewire\Profile\Show as ProfileShow;
use App\Livewire\Wallet\Index as WalletIndex;
use App\Models\User;
use App\Support\PlayerNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('player numbers are grouped the way players are shown them', function (mixed $raw, ?string $shown) {
    expect(PlayerNumber::format($raw))->toBe($shown);
})->with([
    'KadiApi bare digits' => ['12345671', '1234 5671'],
    'surrounding spaces' => [' 12345671 ', '1234 5671'],
    'unexpected shape is shown as sent' => ['123-45', '123-45'],
    'missing' => [null, null],
    'blank' => ['', null],
]);

test('the wallet shows the player number and the M-Pesa paybill card with the active paybill and account number', function () {
    config(['kadi.paybill' => '4151665']);
    Http::fake();
    $user = User::factory()->create(['linked_id' => 8101, 'account_no' => 'KK-LOCAL']);
    Cache::put("kadi.customer.{$user->id}", ['balance' => 10, 'account_no' => 'KK-8FAV7WJY', 'player_no' => '12345671'], now()->addHour());

    Livewire::actingAs($user)->test(WalletIndex::class)
        ->assertSeeHtml('data-test="wallet-player-no">1234 5671<')
        ->assertSeeHtml('data-test="mpesa-paybill-card"')
        ->assertSeeHtml('data-test="mpesa-paybill-number">4151665<')
        ->assertSeeHtml('data-test="mpesa-paybill-account">KK-8FAV7WJY<');
});

test('the paybill card falls back to the local account number while KadiApi has not loaded', function () {
    config(['kadi.paybill' => '4151665']);
    Http::fake();
    $user = User::factory()->create(['linked_id' => 8102, 'account_no' => 'KK-LOCAL']);

    Livewire::actingAs($user)->test(WalletIndex::class)
        ->assertSet('needsLoad', true)
        ->assertSeeHtml('data-test="mpesa-paybill-account">KK-LOCAL<');

    Http::assertNothingSent();
});

test('the paybill card is hidden when no paybill is configured', function () {
    config(['kadi.paybill' => null]);
    Http::fake();
    $user = User::factory()->create(['linked_id' => 8103]);
    Cache::put("kadi.customer.{$user->id}", ['balance' => 10, 'account_no' => 'KK-1', 'player_no' => '12345671'], now()->addHour());

    Livewire::actingAs($user)->test(WalletIndex::class)
        ->assertDontSeeHtml('data-test="mpesa-paybill-card"');
});

test('the profile shows the player number', function () {
    Http::fake();
    $user = User::factory()->create(['linked_id' => 8104]);
    Cache::put("kadi.customer.{$user->id}", ['account_no' => 'KK-1', 'player_no' => '87654321'], now()->addHour());

    Livewire::actingAs($user)->test(ProfileShow::class)
        ->assertSeeHtml('data-test="profile-player-no">8765 4321<');
});

test('the profile shows a dash when KadiApi has no player number yet', function () {
    Http::fake();
    $user = User::factory()->create(['linked_id' => 8105]);
    Cache::put("kadi.customer.{$user->id}", ['account_no' => 'KK-1'], now()->addHour());

    Livewire::actingAs($user)->test(ProfileShow::class)
        ->assertSeeHtml('data-test="profile-player-no">—<');
});

test('saving the profile keeps the player number when the update response leaves it out', function () {
    Http::fake([
        'https://api.kadi-kings.co.ke/api/v1/customers/*' => Http::response(['data' => ['id' => 8106, 'name' => 'Wanjiru']]),
    ]);
    $user = User::factory()->create(['linked_id' => 8106, 'name' => 'Wanjiru']);
    Cache::put("kadi.customer.{$user->id}", ['account_no' => 'KK-1', 'player_no' => '87654321', 'balance' => 40], now()->addHour());

    Livewire::actingAs($user)->test(ProfileShow::class)
        ->call('updateProfile')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-test="profile-player-no">8765 4321<');

    expect(Cache::get("kadi.customer.{$user->id}"))
        ->player_no->toBe('87654321')
        ->balance->toBe(40);
});

test('the home page and FAQ show the paybill from MPESA_SHORTCODE', function () {
    config(['kadi.paybill' => '4151665']);

    $this->get(route('home'))->assertOk()->assertSee('M-PESA PAYBILL: 4151665');
    $this->get(route('faq'))->assertOk()->assertSee('business number 4151665');
});

test('the home page and FAQ leave the paybill out when it is not set', function () {
    config(['kadi.paybill' => null]);

    $this->get(route('home'))->assertOk()->assertDontSee('M-PESA PAYBILL');
    $this->get(route('faq'))->assertOk()->assertDontSee('business number');
});

test('the sportsbook M-Pesa card shows the paybill and the signed-in player account number', function () {
    config(['kadi.paybill' => '4151665']);
    $this->actingAs(User::factory()->create(['account_no' => 'KK-SPORT1']));

    $html = view('partials.mpesa-card')->render();

    expect($html)
        ->toContain('data-test="sportsbook-paybill">4151665<')
        ->toContain('data-test="sportsbook-account-no">KK-SPORT1<')
        ->not->toContain('YOUR PHONE NO')
        ->not->toContain('247247');
});

test('the sportsbook M-Pesa card tells guests where to find their account number', function () {
    config(['kadi.paybill' => '4151665']);

    expect(view('partials.mpesa-card')->render())
        ->toContain('Your Kadi account no.')
        ->toContain('Sign in to see your account number')
        ->not->toContain('data-test="sportsbook-account-no"');
});

test('the sportsbook M-Pesa card is hidden when no paybill is set', function () {
    config(['kadi.paybill' => null]);

    expect(view('partials.mpesa-card')->render())->not->toContain('sportsbook-mpesa-card');
});
