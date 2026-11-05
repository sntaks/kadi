<?php

use App\Livewire\Profile\Show;
use App\Models\BlockedName;
use App\Models\User;
use App\Support\PlayerName;
use Laravel\Fortify\Features;
use Livewire\Livewire;

/**
 * Player names: 4-12 characters, letters/digits/spaces/_ ! - only, changeable once a year, and a player whose
 * name breaks the rules must pick a new one at their next login.
 */
function registerAs(string $name)
{
    return test()->post(route('register.store'), [
        'name' => $name,
        'email' => 'new@example.com',
        'phone' => '0700123456',
        'password' => 'password',
        'password_confirmation' => 'password',
        'age_confirmed' => '1',
        'terms' => '1',
    ]);
}

// --- Format ----------------------------------------------------------------

test('format errors', function (string $name, bool $valid) {
    expect(PlayerName::formatError($name) === null)->toBe($valid);
})->with([
    'minimum length' => ['abcd', true],
    'maximum length' => ['abcdefghijkl', true],
    'allowed symbols' => ['a_b-c!1', true],
    'too short' => ['abc', false],
    'too long' => ['abcdefghijklm', false],
    'single space' => ['abc def', true],
    'double space' => ['abc  def', false],
    'leading space' => [' abcdef', false],
    'trailing space' => ['abcdef ', false],
    'other symbol' => ['abc.def', false],
    'emoji' => ['abcdef😀', false],
    'accents' => ['Wanjirú1', false],
    'trailing newline' => ["abcdef\n", false],
    'empty' => ['', false],
]);

test('registration enforces the format', function (string $name) {
    test()->skipUnlessFortifyHas(Features::registration());

    registerAs($name)->assertSessionHasErrors('name');

    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
})->with(['short' => 'abc', 'long' => 'abcdefghijklm', 'double space' => 'Jane  Doe', 'emoji' => 'player😀1']);

test('registration accepts a valid name', function () {
    test()->skipUnlessFortifyHas(Features::registration());

    registerAs('Player_1!')->assertSessionHasNoErrors();

    expect(User::where('email', 'new@example.com')->firstOrFail()->name_changed_at)->toBeNull();
});

// --- Once a year -----------------------------------------------------------

test('a name can be changed once, which starts the one-year wait', function () {
    $user = User::factory()->create(['name' => 'OldName1', 'linked_id' => null]);

    Livewire::actingAs($user)->test(Show::class)
        ->set('name', 'NewName1')
        ->call('updateProfile')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('NewName1')
        ->and($user->fresh()->name_changed_at)->not->toBeNull();

    Livewire::actingAs($user->fresh())->test(Show::class)
        ->set('name', 'Another1')
        ->call('updateProfile')->assertHasErrors('name');

    expect($user->fresh()->name)->toBe('NewName1');
});

test('the name can be changed again after a year', function () {
    $user = User::factory()->create(['name' => 'OldName1', 'linked_id' => null]);
    $user->forceFill(['name_changed_at' => now()->subYear()->subDay()])->save();

    Livewire::actingAs($user)->test(Show::class)
        ->set('name', 'Another1')
        ->call('updateProfile')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Another1');
});

test('saving other details is fine during the wait when the name is unchanged', function () {
    $user = User::factory()->create(['name' => 'OldName1', 'linked_id' => null, 'phone' => null]);
    $user->forceFill(['name_changed_at' => now()->subMonth()])->save();

    Livewire::actingAs($user)->test(Show::class)
        ->set('phoneNo', '0712345678')
        ->call('updateProfile')->assertHasNoErrors();

    expect($user->fresh()->phone)->toBe('254712345678')->and($user->fresh()->name)->toBe('OldName1');
});

test('the profile page enforces the format and the yearly limit', function () {
    $user = User::factory()->create(['name' => 'OldName1', 'linked_id' => null]);

    Livewire::actingAs($user)->test(Show::class)
        ->set('name', 'no')->call('updateProfile')->assertHasErrors('name');

    Livewire::actingAs($user)->test(Show::class)
        ->set('name', 'NewName1')->call('updateProfile')->assertHasNoErrors();

    Livewire::actingAs($user->fresh())->test(Show::class)
        ->set('name', 'Another1')->call('updateProfile')->assertHasErrors('name');

    expect($user->fresh()->name)->toBe('NewName1');
});

// --- Forced rename at next login ------------------------------------------

test('a player with a non-compliant name is sent to rename at login', function (string $badName) {
    $user = User::factory()->create(['name' => $badName]);

    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->get(route('wallet'))->assertRedirect(route('name.edit'));
    test()->get(route('name.edit'))->assertOk();
})->with(['double space' => 'Jane  Smith', 'too short' => 'Jo', 'too long' => 'Abcdefghijklmnop', 'emoji' => 'Player😀12']);

test('a player whose name is now blocked is sent to rename at login', function () {
    BlockedName::create(['term' => 'champion']);
    $user = User::factory()->create(['name' => 'Champion1']);

    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->get(route('wallet'))->assertRedirect(route('name.edit'));
});

test('a player with a good name is not sent anywhere', function () {
    $user = User::factory()->create(['name' => 'GoodName1']);

    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->get(route('wallet'))->assertOk();
    test()->get(route('name.edit'))->assertRedirect(route('home'));
});

test('non-safe requests are refused until the name is changed', function () {
    $user = User::factory()->create(['name' => 'Jane Smith Junior']);

    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->post(route('profile.picture'))->assertForbidden();
});

test('the rename page validates, saves and releases the player', function () {
    $user = User::factory()->create(['name' => 'Jane Smith Junior']);
    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->post(route('name.update'), ['name' => 'Jane Smith Junior'])->assertSessionHasErrors('name');
    test()->post(route('name.update'), ['name' => 'Jan'])->assertSessionHasErrors('name');

    test()->post(route('name.update'), ['name' => 'Jane Smith'])->assertRedirect();

    expect($user->fresh()->name)->toBe('Jane Smith');
    test()->get(route('wallet'))->assertOk();
});

test('a forced rename does not start the one-year wait', function () {
    $user = User::factory()->create(['name' => 'Jane Smith Junior']);
    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->post(route('name.update'), ['name' => 'Jane Smith']);

    expect($user->fresh()->name_changed_at)->toBeNull();
});

test('a forced rename is allowed even inside the one-year wait', function () {
    $user = User::factory()->create(['name' => 'Jane Smith Junior']);
    $user->forceFill(['name_changed_at' => now()->subMonth()])->save();
    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->post(route('name.update'), ['name' => 'Jane Smith'])->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Jane Smith');
});

test('a forced player cannot keep the offending blocked name', function () {
    BlockedName::create(['term' => 'champion']);
    $user = User::factory()->create(['name' => 'Champion1']);
    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->post(route('name.update'), ['name' => 'Champion1'])->assertSessionHasErrors('name');
});

// --- Client-side validation ------------------------------------------------

test('the name field renders client-side validation attributes', function () {
    test()->get(route('register'))
        ->assertOk()
        ->assertSee('minlength="4"', false)
        ->assertSee('maxlength="12"', false)
        ->assertSee('pattern="[A-Za-z0-9_!\-]+( [A-Za-z0-9_!\-]+)*"', false);
});

// --- Suggested name --------------------------------------------------------

test('a suggestion is made from the old name', function (string $old, string $expected) {
    expect(PlayerName::suggest($old))->toBe($expected);
})->with([
    'first words that fit' => ['Wanjiru Kamau Njeri', 'Wanjiru'],
    'whole name fits after cleaning' => ['Jane   Doe', 'Jane Doe'],
    'accents and emojis dropped' => ['Wanjirú😀', 'Wanjiru'],
    'single long word is cut' => ['Abcdefghijklmnop', 'Abcdefghijkl'],
    'too little left' => ['Jo', ''],
    'nothing usable' => ['😀😀', ''],
]);

test('a blocked name yields no suggestion', function () {
    BlockedName::create(['term' => 'champion']);

    expect(PlayerName::suggest('Champion Kamau'))->toBe('');
});

test('the rename page pre-fills a suggestion', function () {
    $user = User::factory()->create(['name' => 'Wanjiru Kamau Njeri']);
    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    test()->get(route('name.edit'))->assertOk()->assertSee('value="Wanjiru"', false);
});
