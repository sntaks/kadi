<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PushMessageNotification;
use Illuminate\Support\Str;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Sends a real test push straight through the web push channel, immediately (no queue
 * worker needed) and returns per-device outcomes. Used by `php artisan push:test` and the
 * "Send test notification" button, so both behave identically.
 */
class PushTestSender
{
    /**
     * The button is for developers and admins: available to anyone signed in on the environments
     * in config('kadi.push.test_environments') (local and staging), and to admin roles everywhere.
     */
    public function allowedFor(User $user): bool
    {
        return app()->environment(config('kadi.push.test_environments', ['local', 'staging'])) || $user->isAdmin();
    }

    public function configured(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    /**
     * @return array{devices: int, delivered: int, expired: int, failed: int}
     *
     * @throws \Throwable when the push cannot be built or signed (e.g. missing VAPID keys or OpenSSL config)
     */
    public function send(User $user, ?string $body = null): array
    {
        $result = ['devices' => $user->pushSubscriptions()->count(), 'delivered' => 0, 'expired' => 0, 'failed' => 0];

        if ($result['devices'] === 0) {
            return $result;
        }

        $notification = new PushMessageNotification(
            title: 'Kadi test notification',
            // 199 characters plus a one-character ellipsis keeps the whole body within 200.
            body: Str::limit(trim((string) $body) !== '' ? trim((string) $body) : 'Push is working on this device.', 199, '…'),
            url: '/account#notifications',
            tag: 'push-test',
            ttl: 300,
        );

        // A fresh model: the channel reads $user->pushSubscriptions, which may already be cached.
        $reports = app(WebPushChannel::class)->send($user->fresh(), $notification);

        /** @var MessageSentReport $report */
        foreach ($reports as $report) {
            if ($report->isSuccess()) {
                $result['delivered']++;
            } elseif ($report->isSubscriptionExpired()) {
                $result['expired']++; // the package has already removed the row
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }
}
