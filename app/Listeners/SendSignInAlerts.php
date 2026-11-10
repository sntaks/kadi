<?php

namespace App\Listeners;

use App\Events\SignInRefused;
use App\Mail\SecurityAlertEmail;
use App\Models\User;
use App\Notifications\PushMessageNotification;
use App\Notifications\SecurityAlert;
use App\Support\DeviceName;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the player about every attempt to sign in to their account: a new sign-in, one refused because another device
 * is signed in, and a wrong password (at most one of those per 15 minutes per account, so nobody can flood a player's
 * inbox). Same channels as SendSecurityNotification: in-app notice, e-mail, and push when they turned it on.
 *
 * Auto-discovered (one handle* method per event); never register it with Event::listen(). Messages name the browser,
 * the way in and the time only: no IP address, password, code or token.
 */
class SendSignInAlerts
{
    public const FAILED_THROTTLE_MINUTES = 15;

    private const METHODS = [
        'auth.google.callback' => 'Google',
        'auth.google.complete.store' => 'Google',
        'passkey.login' => 'a passkey',
    ];

    public function handleLogin(Login $event): void
    {
        // A remember-me cookie on a device that was already signed in, and the sign-in that follows creating an
        // account, are not new sign-ins.
        if (! $event->user instanceof User || $event->user->wasRecentlyCreated || Auth::guard($event->guard)->viaRemember()) {
            return;
        }

        $this->send($event->user, 'New sign-in to your account with '.$this->method().' from '.$this->device());
    }

    public function handleRefused(SignInRefused $event): void
    {
        $this->send($event->user, 'Someone signed in with your details from '.$this->device().', and was stopped because you are signed in on another device');
    }

    public function handleFailed(Failed $event): void
    {
        if (! $event->user instanceof User || ! Cache::add('sign-in-alert:failed:'.$event->user->id, true, now()->addMinutes(self::FAILED_THROTTLE_MINUTES))) {
            return;
        }

        $this->send($event->user, 'Someone tried to sign in to your account with a wrong password from '.$this->device());
    }

    private function send(User $user, string $message): void
    {
        try {
            $user->notify(new SecurityAlert($message));
            Mail::to($user->email)->queue(new SecurityAlertEmail($user, $message));

            if ($user->pushSubscriptions()->exists()) {
                $user->notify(new PushMessageNotification('Sign-in alert', $message, '/account#security', null, 21600, 'high'));
            }
        } catch (\Throwable $e) {
            // An alert outage must never stop a player signing in.
            Log::error('Sign-in alert failed: '.$e::class);
        }
    }

    private function method(): string
    {
        return self::METHODS[request()->route()?->getName()] ?? 'your password';
    }

    private function device(): string
    {
        return DeviceName::from(request()->userAgent());
    }
}
