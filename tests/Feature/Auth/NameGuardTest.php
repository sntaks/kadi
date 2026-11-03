<?php

use App\Jobs\ProcessVerifiedUser;
use App\Livewire\Profile\Show;
use App\Models\BlockedName;
use App\Models\User;
use App\Services\KadiAccountSync;
use App\Support\NameGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Livewire\Livewire;

/**
 * Two promises: a name the operator has blocked cannot be registered or chosen (Google sign-ups are
 * exempt because Google verified the name), and a name change reaches kadi.accounts.name.
 */
function guardRegistration(string $name, string $email = 'new@example.com')
{
    return test()->post(route('register.store'), [
        'name' => $name,
        'email' => $email,
        'phone' => '0700123456',
        'password' => 'password',
        'password_confirmation' => 'password',
        'age_confirmed' => '1',
        'terms' => '1',
    ]);
}

/** A throwaway stand-in for the game database, so cross-database writes can be observed. */
function fakeKadiAccounts(array $rows = []): void
{
    config(['database.connections.kadi' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('kadi');

    Schema::connection('kadi')->create('accounts', function ($t) {
        $t->integer('id')->primary();
        $t->string('name');
        $t->string('email');
    });

    foreach ($rows as $row) {
        DB::connection('kadi')->table('accounts')->insert($row);
    }
}

// --- Matching --------------------------------------------------------------

test('names are normalised so simple evasions collapse to the same thing', function (string $input, string $expected) {
    expect(NameGuard::normalise($input))->toBe($expected);
})->with([
    'case' => ['ADMIN', 'admin'],
    'accents' => ['Ádmïn', 'admin'],
    'lookalikes' => ['4dm1n', 'admin'],
    'symbols' => ['@dm!n', 'admin'],
    'stretched' => ['adminnn', 'admin'],
    'separators become spaces' => ['kadi_support', 'kadi suport'],
    'no letters at all' => ['---', ''],
]);

test('without look-alikes digits and symbols are simply dropped', function () {
    expect(NameGuard::normalise('Admin2026', lookalikes: false))->toBe('admin')
        ->and(NameGuard::normalise('Admin!', lookalikes: false))->toBe('admin')
        ->and(NameGuard::normalise('Admin2026'))->toBe('admin o');
});

test('the starter list blocks impersonation, however it is dressed up', function (string $name) {
    expect(NameGuard::isBlocked($name))->toBeTrue("{$name} should be blocked");
})->with([
    'Admin', 'ADMIN', 'admin', 'Kadi Admin', 'The Admin', '4dm1n', 'a.d-m.i n', 'adminnn', 'k a d i', 'Kadi', 'kadionline',
    'Customer Care', 'customercare', 'Kadi_Support', 'Support Team', 'Moderator', 'System', 'Root',
    'Admin123', 'Admin!', 'Kadi2026', 'xX_Admin_Xx', 'Admin1', '1Admin', 'Support 24/7',
]);

test('ordinary names, including ones that only contain a blocked word inside another word, are fine', function (string $name) {
    expect(NameGuard::isBlocked($name))->toBeFalse("{$name} should be allowed");
})->with([
    'John Doe', 'WanjiruK', 'Sadmin', 'Administration Ola', 'Rooted Tree', 'Kadiri Musa', 'Ownership Sam', 'Systematic Jim', 'Otieno', 'Mary-Jane O\'Neil',
]);

test('a contains entry blocks the term anywhere, a word entry only as a whole word', function () {
    BlockedName::create(['term' => 'badword', 'match' => 'contains']);
    BlockedName::create(['term' => 'boss', 'match' => 'word']);

    expect(NameGuard::isBlocked('xxbadwordxx'))->toBeTrue()
        ->and(NameGuard::isBlocked('Bad Word'))->toBeTrue()      // spacing tricks do not help
        ->and(NameGuard::isBlocked('Big Boss'))->toBeTrue()
        ->and(NameGuard::isBlocked('Bosson'))->toBeFalse();
});

test('terms are stored normalised, and the list changes take effect immediately', function () {
    expect(NameGuard::isBlocked('Fancy Person'))->toBeFalse();

    $blocked = BlockedName::create(['term' => 'F4NCY  Person']);
    expect($blocked->term)->toBe('fancy person')
        ->and(NameGuard::isBlocked('Fancy Person'))->toBeTrue();   // cache was refreshed by the save

    $blocked->delete();
    expect(NameGuard::isBlocked('Fancy Person'))->toBeFalse();     // and by the delete
});

test('an empty list blocks nothing', function () {
    BlockedName::query()->delete();

    expect(NameGuard::isBlocked('Admin'))->toBeFalse();
});

// --- Registration ----------------------------------------------------------

test('registration refuses a blocked name, tells the player nothing about the list, and creates nobody', function () {
    test()->skipUnlessFortifyHas(Features::registration());

    guardRegistration('Admin1')->assertSessionHasErrors('name');

    expect(session('errors')->first('name'))->toBe('That name is not available. Please use your own name.')
        ->and(session('errors')->first('name'))->not->toContain('admin');
    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
    test()->assertGuest();
});

test('registration still accepts a normal name', function () {
    test()->skipUnlessFortifyHas(Features::registration());

    guardRegistration('WanjiruK')->assertSessionHasNoErrors();

    test()->assertAuthenticated();
});

test('a name added to the list later is refused at registration straight away', function () {
    test()->skipUnlessFortifyHas(Features::registration());

    guardRegistration('Champion1', 'a@example.com')->assertSessionHasNoErrors();
    auth()->logout();

    BlockedName::create(['term' => 'champion']);

    guardRegistration('Champion1', 'b@example.com')->assertSessionHasErrors('name');
});

// --- Google sign-up is exempt ----------------------------------------------

test('a google sign-up keeps the name google verified, even if it is on the list', function () {
    Queue::fake();

    test()->withSession(['google.pending' => [
        'id' => 'google-999', 'name' => 'Admin', 'email' => 'google-admin@example.com', 'avatar' => null,
        'expires_at' => now()->addMinutes(10)->getTimestamp(),
    ]])->post(route('auth.google.complete.store'), ['age_confirmed' => '1', 'terms' => '1'])->assertSessionHasNoErrors();

    $user = User::where('email', 'google-admin@example.com')->firstOrFail();
    expect($user->name)->toBe('Admin')->and($user->google_id)->toBe('google-999');
    Queue::assertPushed(ProcessVerifiedUser::class);
});

// --- Changing the name: the account (profile) page ---------------------------

test('the profile page refuses a blocked new name', function () {
    $user = User::factory()->create(['name' => 'RealPerson', 'linked_id' => null]);

    Livewire::actingAs($user)->test(Show::class)
        ->set('name', 'Kadi_Official')
        ->call('updateProfile')
        ->assertHasErrors('name');

    expect($user->fresh()->name)->toBe('RealPerson');
});

test('the profile page copies a changed name to kadi.accounts', function () {
    fakeKadiAccounts([['id' => 600, 'name' => 'Old', 'email' => 'p@example.com']]);
    $user = User::factory()->create(['name' => 'OldName1', 'email' => 'p@example.com', 'linked_id' => null]);

    Livewire::actingAs($user)->test(Show::class)
        ->set('name', 'OtienoJnr')
        ->call('updateProfile')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('OtienoJnr')
        ->and(DB::connection('kadi')->table('accounts')->where('email', 'p@example.com')->value('name'))->toBe('OtienoJnr');
});

test('saving without changing the name is never blocked, even if the list grew since they joined', function () {
    $user = User::factory()->create(['name' => 'Kadi_Official', 'linked_id' => null]);

    Livewire::actingAs($user)->test(Show::class)->call('updateProfile')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Kadi_Official');
});

test('a game database that is down never stops a profile save', function () {
    config(['database.connections.kadi' => ['driver' => 'sqlite', 'database' => '/nonexistent/kadi.sqlite', 'prefix' => '']]);
    DB::purge('kadi');
    $user = User::factory()->create(['name' => 'OldName1', 'linked_id' => null]);

    Livewire::actingAs($user)->test(Show::class)->set('name', 'StillSaved')->call('updateProfile')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('StillSaved');
});

// --- The helper ------------------------------------------------------------

test('the account name is the first word of the full name', function (string $full, string $expected) {
    expect(KadiAccountSync::accountName($full))->toBe($expected);
})->with([['Wanjiru Kamau', 'Wanjiru'], ['  Brian   Otieno ', 'Brian'], ['Madonna', 'Madonna'], ['', '']]);

// --- Operator commands -----------------------------------------------------

test('blocked-names:add blocks a name at once, and stores it normalised', function () {
    test()->artisan('blocked-names:add', ['terms' => ['Big B0SS', 'Grand Wizard'], '--reason' => 'impersonation'])->assertSuccessful();

    expect(NameGuard::isBlocked('big boss'))->toBeTrue()
        ->and(NameGuard::isBlocked('Grand Wizard'))->toBeTrue()
        ->and(BlockedName::where('term', NameGuard::normalise('Big Boss'))->value('reason'))->toBe('impersonation');
});

test('blocked-names:add --contains makes a broad entry, and re-adding updates rather than duplicates', function () {
    test()->artisan('blocked-names:add', ['terms' => ['forbiddenword']])->assertSuccessful();
    expect(NameGuard::isBlocked('xxforbiddenwordxx'))->toBeFalse();

    test()->artisan('blocked-names:add', ['terms' => ['forbiddenword'], '--contains' => true])->assertSuccessful();

    expect(NameGuard::isBlocked('xxforbiddenwordxx'))->toBeTrue()
        ->and(BlockedName::where('term', NameGuard::normalise('forbiddenword'))->count())->toBe(1);
});

test('blocked-names:add skips a term with no letters', function () {
    test()->artisan('blocked-names:add', ['terms' => ['---']])->expectsOutputToContain('no letters')->assertSuccessful();

    expect(BlockedName::where('term', '')->exists())->toBeFalse();
});

test('blocked-names:remove unblocks, and complains about unknown entries', function () {
    BlockedName::create(['term' => 'temporary']);
    expect(NameGuard::isBlocked('Temporary'))->toBeTrue();

    test()->artisan('blocked-names:remove', ['terms' => ['Temporary']])->assertSuccessful();
    expect(NameGuard::isBlocked('Temporary'))->toBeFalse();

    test()->artisan('blocked-names:remove', ['terms' => ['never-there']])->assertFailed();
});

test('blocked-names:list shows the entries', function () {
    test()->artisan('blocked-names:list')->expectsOutputToContain('kadionline')->assertSuccessful();

    BlockedName::query()->delete();
    test()->artisan('blocked-names:list')->expectsOutputToContain('empty')->assertSuccessful();
});

test('blocked-names:check tells the operator which entry matched', function () {
    test()->artisan('blocked-names:check', ['name' => 'Kadi Support'])->expectsOutputToContain('blocked')->assertFailed();
    test()->artisan('blocked-names:check', ['name' => 'WanjiruK'])->expectsOutputToContain('is allowed')->assertSuccessful();
});

test('the seeded starter list is in place after migrating', function () {
    expect(BlockedName::count())->toBeGreaterThanOrEqual(10)
        ->and(BlockedName::where('term', 'admin')->exists())->toBeTrue();
});

test('the cached list is what the guard reads, so a request does not hit the database each time', function () {
    NameGuard::terms();

    DB::enableQueryLog();
    NameGuard::isBlocked('Some Name');
    NameGuard::isBlocked('Another Name');

    expect(collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'blocked_names')))->toBeEmpty();
    expect(Cache::has('name-guard.terms'))->toBeTrue();
});
