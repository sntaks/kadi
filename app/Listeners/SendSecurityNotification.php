<?php

namespace App\Listeners;

use App\Events\PasswordChanged;
use App\Mail\SecurityAlertEmail;
use App\Notifications\PushMessageNotification;
use App\Notifications\SecurityAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * Emails the user about high-impact account security changes.
 *
 * Runs synchronously so it can safely receive laravel/passkeys'
 * PasskeyDeleted event (its deleted model cannot survive queue
 * serialization); delivery itself stays async because the mailable
 * implements ShouldQueue.
 *
 * Messages describe what changed and when — they never contain
 * secrets, codes, or links that could be abused.
 */
class SendSecurityNotification
{
    private const MESSAGES = [
        TwoFactorAuthenticationConfirmed::class => 'Two-factor authentication was enabled on your account',
        TwoFactorAuthenticationDisabled::class => 'Two-factor authentication was disabled on your account',
        PasskeyRegistered::class => 'A new passkey was added to your account',
        PasskeyDeleted::class => 'A passkey was removed from your account',
        PasswordChanged::class => 'Your account password was changed',
    ];

    public function handle(object $event): void
    {
        $message = self::MESSAGES[$event::class] ?? null;

        if ($message === null || ! isset($event->user)) {
            return;
        }

        try {
            $event->user->notify(new SecurityAlert($message));

            Mail::to($event->user->email)->queue(
                new SecurityAlertEmail($event->user, $message)
            );

            // Additive: the same non-secret text as a queued push, only for users who turned
            // notifications on. High urgency (this is the one push worth waking a phone for) and
            // no tag, so a newer alert never replaces an older one still on screen.
            if ($event->user->pushSubscriptions()->exists()) {
                $event->user->notify(new PushMessageNotification('Security alert', $message, '/account#security', null, 21600, 'high'));
            }
        } catch (\Throwable $e) {
            // A mail outage must never break the underlying security flow.
            Log::error('Security notification failed: '.$e->getMessage());
        }
    }
}
