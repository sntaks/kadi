<?php

use App\Livewire\PhoneRequired;
use App\Livewire\Wallet\DepositSheet;
use App\Livewire\Wallet\Index;
use App\Models\User;
use App\Support\PhoneOtp;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['services.textsms.api_key' => 'test-key', 'services.textsms.partner_id' => '14938']);
});

function fakeSms(int $code = 200): void
{
    Http::fake([
        'sms.textsms.co.ke/*' => Http::response(['responses' => [['respose-code' => $code, 'response-description' => 'Success']]]),
        '*' => Http::response(['data' => []]),
    ]);
}

function lastSmsCode(): string
{
    $sms = Http::recorded(fn (Request $request) => str_contains($request->url(), 'textsms'))->last()[0];
    preg_match('/\d{6}/', $sms['message'], $match);

    return $match[0];
}

function unverifiedPlayer(array $attributes = []): User
{
    return User::factory()->phoneUnverified()->create(['phone' => '254712345678', ...$attributes]);
}

test('a short code SMS goes to 254… through TextSMS', function () {
    fakeSms();

    expect(app(PhoneOtp::class)->send(unverifiedPlayer()))->toBe(PhoneOtp::SENT);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://sms.textsms.co.ke/api/services/sendsms/'
        && $request['mobile'] === '254712345678'
        && $request['apikey'] === 'test-key'
        && $request['partnerID'] === '14938'
        && preg_match('/^Kadi code: \d{6}\. Valid 10 min\. Never share it\.$/', $request['message']) === 1);
});

test('the right code verifies the phone', function () {
    fakeSms();
    $user = unverifiedPlayer();

    app(PhoneOtp::class)->send($user);

    expect(app(PhoneOtp::class)->verify($user, lastSmsCode()))->toBe(PhoneOtp::VERIFIED)
        ->and($user->fresh()->hasVerifiedPhone())->toBeTrue();
});

test('a wrong code is refused, and 5 wrong codes burn it', function () {
    fakeSms();
    $user = unverifiedPlayer();
    app(PhoneOtp::class)->send($user);
    $right = lastSmsCode();
    $wrong = $right === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $i) {
        expect(app(PhoneOtp::class)->verify($user, $wrong))->toBe(PhoneOtp::INVALID);
    }

    expect(app(PhoneOtp::class)->verify($user, $wrong))->toBe(PhoneOtp::TOO_MANY)
        ->and(app(PhoneOtp::class)->verify($user, $right))->toBe(PhoneOtp::EXPIRED)
        ->and($user->fresh()->phone_verified_at)->toBeNull();
});

test('a code expires after 10 minutes', function () {
    fakeSms();
    $user = unverifiedPlayer();
    app(PhoneOtp::class)->send($user);
    $code = lastSmsCode();

    $this->travel(11)->minutes();

    expect(app(PhoneOtp::class)->verify($user, $code))->toBe(PhoneOtp::EXPIRED);
});

test('resends wait 60 seconds and are capped per hour', function () {
    fakeSms();
    $user = unverifiedPlayer();
    $otp = app(PhoneOtp::class);

    expect($otp->send($user))->toBe(PhoneOtp::SENT)
        ->and($otp->send($user))->toBe(PhoneOtp::COOLDOWN);

    foreach (range(2, 5) as $i) {
        $this->travel(61)->seconds();
        expect($otp->send($user))->toBe(PhoneOtp::SENT);
    }

    $this->travel(61)->seconds();
    expect($otp->send($user))->toBe(PhoneOtp::LIMITED);
});

test('a TextSMS failure is reported and no code is kept', function () {
    fakeSms(1004);
    $user = unverifiedPlayer();

    expect(app(PhoneOtp::class)->send($user))->toBe(PhoneOtp::FAILED)
        ->and(app(PhoneOtp::class)->hasPendingCode($user))->toBeFalse();
});

test('the code, the message and the full number never reach the logs', function () {
    fakeSms(500);
    Log::spy();

    app(PhoneOtp::class)->send(unverifiedPlayer());

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, '2547****5678')
        && ! str_contains($message, '254712345678')
        && ! str_contains($message, 'Kadi code'));
});

test('the modal sends a code and confirms the phone', function () {
    fakeSms();
    $user = unverifiedPlayer();

    $modal = Livewire::actingAs($user)->test(PhoneRequired::class)
        ->call('open', 'deposit')
        ->assertSet('show', true)
        ->assertSet('step', 'code')
        ->call('sendCode');

    $modal->set('code', lastSmsCode())
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSet('show', false)
        ->assertDispatched('phone-verified')
        ->assertDispatched('phone-saved');

    expect($user->fresh()->hasVerifiedPhone())->toBeTrue();
});

test('a wrong code in the modal shows an error', function () {
    fakeSms();
    $user = unverifiedPlayer();

    $modal = Livewire::actingAs($user)->test(PhoneRequired::class)->call('open', 'verify')->call('sendCode');
    $wrong = lastSmsCode() === '000000' ? '111111' : '000000';

    $modal->set('code', $wrong)->call('verify')->assertHasErrors('code')->assertNotDispatched('phone-verified');
});

test('an unverified number can be changed, and the change is synced', function () {
    fakeSms();
    $user = unverifiedPlayer(['linked_id' => 77]);

    Livewire::actingAs($user)->test(PhoneRequired::class)
        ->call('open', 'verify')
        ->call('changeNumber')
        ->assertSet('step', 'phone')
        ->set('phone', '0722000111')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'code');

    expect($user->fresh()->phone)->toBe('254722000111');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request['phone_no'] === '254722000111');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'textsms') && $request['mobile'] === '254722000111');
});

test('a verified number can never be changed from the modal', function () {
    fakeSms();
    $user = User::factory()->create(['phone' => '254712345678']);

    Livewire::actingAs($user)->test(PhoneRequired::class)
        ->set('phone', '0722000111')
        ->call('save');

    expect($user->fresh()->phone)->toBe('254712345678');
    Http::assertNothingSent();
});

test('a code sent to the old number does not verify the new one', function () {
    fakeSms();
    $user = unverifiedPlayer();
    app(PhoneOtp::class)->send($user);
    $code = lastSmsCode();

    $user->update(['phone' => '0722000111']);

    expect(app(PhoneOtp::class)->verify($user->fresh(), $code))->toBe(PhoneOtp::EXPIRED);
});

test('deposits wait for a confirmed phone', function () {
    Http::fake();
    $user = unverifiedPlayer(['linked_id' => 5151]);

    Livewire::actingAs($user)->test(DepositSheet::class)
        ->dispatch('open-deposit')
        ->set('amount', '500')
        ->call('send')
        ->assertSet('stage', null)
        ->assertDispatched('open-phone-required', purpose: 'deposit');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/deposits'));
});

test('withdrawals wait for a confirmed phone and resume afterwards', function () {
    Http::fake();
    $user = unverifiedPlayer(['linked_id' => 5151]);
    cache()->put("kadi.customer.{$user->id}", ['balance' => 5000], now()->addHour());

    $wallet = Livewire::actingAs($user)->test(Index::class)
        ->call('openWithdraw')
        ->set('withdrawAmount', '100')
        ->call('requestWithdraw')
        ->assertSet('confirmingWithdraw', false)
        ->assertSet('showWithdrawModal', false)
        ->assertDispatched('open-phone-required', purpose: 'withdraw');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/withdraw/'));

    $user->forceFill(['phone_verified_at' => now()])->save();
    $wallet->dispatch('phone-saved')->assertSet('showWithdrawModal', true);
});

test('the site notice asks unverified players to confirm their number', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    $this->actingAs(unverifiedPlayer())->get(route('wallet'))->assertSee('data-test="notice-phone"', false);
    $this->actingAs(User::factory()->create(['phone' => '254799000111', 'phone_verified_at' => now()]))->get(route('wallet'))->assertDontSee('data-test="notice-phone"', false);
});

test('before a code is sent the modal offers a clear send button', function () {
    fakeSms();

    Livewire::actingAs(unverifiedPlayer())->test(PhoneRequired::class)
        ->call('open', 'deposit')
        ->assertSee('data-test="otp-send"', false)
        ->assertSee('We will text a code to 2547****5678.')
        ->assertDontSee('data-test="otp-input"', false);
});

test('after a code is sent the modal asks for it', function () {
    fakeSms();
    $user = unverifiedPlayer();

    Livewire::actingAs($user)->test(PhoneRequired::class)
        ->call('open', 'deposit')
        ->call('sendCode')
        ->assertSee('data-test="otp-input"', false)
        ->assertDontSee('data-test="otp-send"', false)
        ->assertSee('We sent a code to 2547****5678.');
});
