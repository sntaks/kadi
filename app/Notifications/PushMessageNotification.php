<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A generic, queued web push. Use it directly, or copy its toWebPush() for a
 * feature-specific notification:
 *
 *     $user->notify(new PushMessageNotification('Deposit received', 'KES 500 is in your vault.', '/wallet'));
 *
 * Every push MUST render a visible notification (iOS revokes subscriptions that
 * receive silent pushes), so title and body are required. Keep payloads free of
 * balances and other sensitive data: a device can outlive a login.
 */
class PushMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job is marked failed. */
    public int $tries = 3;

    /** Seconds to wait between attempts. @var array<int, int> */
    public array $backoff = [10, 60];

    /**
     * Longest a single attempt may run. The web push client waits up to 30s per request, so a
     * hung push service must never hold a worker for the worker-wide limit. This has to stay
     * below the worker's `--timeout` and the queue connection's `retry_after`
     * (see tests/Feature/Push/PushQueueTest.php and docs/pwa-push.md).
     */
    public int $timeout = 60;

    /**
     * @param  string  $url  Where a tap should go. Only same-origin paths are honoured; anything else falls back to "/".
     * @param  int  $ttl  Seconds the push service may hold the message for an offline device, so stale pushes never arrive hours late.
     * @param  string  $urgency  very-low | low | normal | high. Anything else falls back to normal. Use high sparingly (it can wake a sleeping phone).
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/',
        public ?string $tag = null,
        public int $ttl = 3600,
        public string $urgency = 'normal',
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Skip users with no registered device, so no job does work for nothing.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists();
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/v2/pwa-icons/icon-192.png')
            ->badge('/v2/pwa-icons/badge-72.png')
            ->data(['url' => $this->sameOriginPath($this->url)])
            ->options([
                'TTL' => $this->ttl,
                'urgency' => in_array($this->urgency, ['very-low', 'low', 'normal', 'high'], true) ? $this->urgency : 'normal',
            ]);

        if ($this->tag !== null && $this->tag !== '') {
            $message->tag($this->tag);
        }

        return $message;
    }

    /**
     * Reduce a target to a same-origin path. Protocol-relative URLs, other hosts,
     * odd schemes and control characters all collapse to the site root, so a push
     * can never send someone to another site (the service worker re-checks too).
     */
    private function sameOriginPath(string $url): string
    {
        $url = trim($url);

        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '/';
        }

        if (str_starts_with($url, '//')) {
            return '/';
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $target = parse_url($url);
        $app = parse_url((string) config('app.url'));

        if ($target === false || $app === false || ! isset($target['scheme'], $target['host'], $app['host'])) {
            return '/';
        }

        $sameOrigin = strtolower($target['scheme']) === strtolower($app['scheme'] ?? 'https')
            && strtolower($target['host']) === strtolower($app['host'])
            && ($target['port'] ?? null) === ($app['port'] ?? null);

        if (! $sameOrigin) {
            return '/';
        }

        return ($target['path'] ?? '/')
            .(isset($target['query']) ? '?'.$target['query'] : '')
            .(isset($target['fragment']) ? '#'.$target['fragment'] : '');
    }
}
