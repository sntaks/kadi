<?php

namespace App\Listeners;

use App\Activity\PlayerActivity;
use App\Events\SignInRefused;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;

/**
 * Sign-ins and sign-outs in the player activity log. Auto-discovered (one handle* method per event); never register
 * it with Event::listen().
 */
class RecordPlayerActivity
{
    private const METHODS = [
        'auth.google.callback' => 'google',
        'auth.google.complete.store' => 'google',
        'passkey.login' => 'passkey',
        'two-factor.login.store' => 'password+2fa',
        'register.store' => 'registration',
    ];

    public function __construct(private PlayerActivity $activity) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $method = Auth::guard($event->guard)->viaRemember() ? 'remember_me' : (self::METHODS[request()->route()?->getName()] ?? 'password');

        $this->activity->record('signed_in', $event->user, ['method' => $method]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->activity->record('signed_out', $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        // A wrong password for an e-mail with no account has nobody to file it under, and the typed e-mail is never kept.
        if ($event->user instanceof User) {
            $this->activity->record('sign_in_failed', $event->user);
        }
    }

    public function handleRefused(SignInRefused $event): void
    {
        $this->activity->record('sign_in_refused', $event->user);
    }
}
