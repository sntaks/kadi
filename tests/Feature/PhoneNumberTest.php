<?php

use App\Livewire\PhoneRequired;
use App\Livewire\Profile\Show;
use App\Livewire\Wallet\DepositSheet;
use App\Models\User;
use App\Rules\KenyanPhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

const PHONE_API_BASE = 'https://api.kadi-kings.co.ke/api/v1/';

dataset('phone inputs', [
    'plus and spaces' => ['+254 712 345 678', '254712345678'],
    'leading zero with hyphens' => ['0712-345-678', '254712345678'],
    'bare nine digits' => ['712345678', '254712345678'],
    'already normalised' => ['254712345678', '254712345678'],
    'airtel 01xx' => ['0112345678', '254112345678'],
    'blank' => ['   ', null],
]);

it('normalises phone numbers', function (string $input, ?string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with('phone inputs');

it('rejects numbers that are not Kenyan mobiles', function (string $input) {
    expect(PhoneNumber::isValid($input))->toBeFalse();
})->with(['too short' => '07123', 'too long' => '2547123456789', 'landline' => '0201234567', 'foreign' => '+14155552671', 'letters' => 'abc']);

it('stores the phone normalised, and null when blank', function () {
    $user = User::factory()->create(['phone' => '+254 712 345 678']);
    expect($user->fresh()->phone)->toBe('254712345678');

    $user->update(['phone' => '']);
    expect($user->fresh()->phone)->toBeNull();
});

it('validates format and uniqueness across notations, ignoring the owner', function () {
    $owner = User::factory()->create(['phone' => '254712345678']);

    $check = fn (string $value, ?int $ignore = null) => Validator::make(['p' => $value], ['p' => new KenyanPhone($ignore)])->passes();

    expect($check('0712 345 678'))->toBeFalse()          // same number, different notation
        ->and($check('+254712345678', $owner->id))->toBeTrue()  // owner re-saving their own number
        ->and($check('0722000111'))->toBeTrue()
        ->and($check('12345'))->toBeFalse();
});

it('saves a valid phone from the modal and sends KadiApi a number without +', function () {
    config(['services.textsms.api_key' => 'test-key', 'services.textsms.partner_id' => '1']);
    Http::fake([
        PHONE_API_BASE.'customers/*' => Http::response(['data' => []]),
        'sms.textsms.co.ke/*' => Http::response(['responses' => [['response-code' => 200]]]),
    ]);
    $user = User::factory()->phoneUnverified()->create(['linked_id' => 77, 'phone' => null]);

    // Saving moves on to the SMS code; the deposit flow resumes only once the number is confirmed.
    Livewire::actingAs($user)->test(PhoneRequired::class)
        ->set('phone', '+254 712 345 678')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('step', 'code')
        ->assertNotDispatched('phone-saved');

    expect($user->fresh()->phone)->toBe('254712345678')
        ->and($user->fresh()->phone_verified_at)->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['phone_no'] === '254712345678');
});

it('rejects a duplicate phone in the modal without calling KadiApi', function () {
    Http::fake();
    User::factory()->create(['phone' => '254712345678']);
    $user = User::factory()->create(['linked_id' => 77, 'phone' => null]);

    Livewire::actingAs($user)->test(PhoneRequired::class)
        ->set('phone', '0712345678')
        ->call('save')
        ->assertHasErrors('phone');

    expect($user->fresh()->phone)->toBeNull();
    Http::assertNothingSent();
});

it('never overwrites a phone that is already set', function () {
    Http::fake();
    $user = User::factory()->create(['phone' => '254712345678']);

    Livewire::actingAs($user)->test(PhoneRequired::class)
        ->set('phone', '0722000111')
        ->call('save');

    expect($user->fresh()->phone)->toBe('254712345678');
    Http::assertNothingSent();
});

it('does not send phone_no from the profile page once a phone is set', function () {
    Http::fake([PHONE_API_BASE.'customers/*' => Http::response(['data' => []])]);
    $user = User::factory()->create(['linked_id' => 77, 'phone' => '254712345678']);

    Livewire::actingAs($user)->test(Show::class)
        ->set('phoneNo', '+254 799 000 111')
        ->call('updateProfile');

    expect($user->fresh()->phone)->toBe('254712345678');
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && ! isset($request['phone_no']));
});

it('opens the phone sheet instead of depositing when the phone is missing', function () {
    Http::fake();
    $user = User::factory()->create(['linked_id' => 5151, 'phone' => null]);

    Livewire::actingAs($user)->test(DepositSheet::class)
        ->dispatch('open-deposit')
        ->set('amount', '500')
        ->call('send')
        ->assertSet('open', false)
        ->assertDispatched('open-phone-required', purpose: 'deposit');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/deposits'));
});
