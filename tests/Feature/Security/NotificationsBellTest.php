<?php

use App\Livewire\NotificationsBell;
use App\Livewire\Profile\Security\TwoFactorPanel;
use App\Models\User;
use App\Notifications\SecurityAlert;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function alertFor(User $user, string $change): DatabaseNotification
{
    $user->notify(new SecurityAlert($change));

    return $user->notifications()->first();
}

test('bell renders unread count and lists notifications', function () {
    $user = User::factory()->create();

    alertFor($user, 'Your account password was changed');
    alertFor($user, 'A passkey was removed from your account');
    $read = alertFor($user, 'Two-factor authentication was enabled on your account');
    $read->markAsRead();

    $this->actingAs($user);

    Livewire::test(NotificationsBell::class)
        ->assertSee('Your account password was changed')
        ->assertSee('A passkey was removed from your account')
        ->assertSeeHtml('>2<')
        ->assertSee(__('Mark all as read'), false);
});

test('bell shows empty state when there are no notifications', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(NotificationsBell::class)
        ->assertSee(__('You are all caught up.'));
});

test('marking all as read clears the badge', function () {
    $user = User::factory()->create();

    alertFor($user, 'Your account password was changed');
    alertFor($user, 'A new passkey was added to your account');

    $this->actingAs($user);

    Livewire::test(NotificationsBell::class)
        ->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('a notification can be marked read individually', function () {
    $user = User::factory()->create();
    $notification = alertFor($user, 'Your account password was changed');

    $this->actingAs($user);

    Livewire::test(NotificationsBell::class)
        ->call('markAsRead', $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('another users notifications are never visible', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $notification = alertFor($owner, 'Owner only alert');

    $this->actingAs($intruder);

    Livewire::test(NotificationsBell::class)
        ->assertDontSee('Owner only alert')
        ->call('markAsRead', $notification->id);

    expect($notification->fresh()->read_at)->toBeNull();
});

test('the layouts render the bell for authenticated users', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('wallet'))
        ->assertOk()
        ->assertSee(__('Notifications'), false);
});

test('security changes still notify through the bell channel', function () {
    Mail::fake();

    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(TwoFactorPanel::class)
        ->call('disableTwoFactor');

    expect($user->fresh()->notifications()->where('type', SecurityAlert::class)->count())->toBe(1);
});
