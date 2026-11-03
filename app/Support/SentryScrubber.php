<?php

namespace App\Support;

use Sentry\Breadcrumb;
use Sentry\Event;

/**
 * Last line of defence before an event leaves for Sentry. Masks private query
 * parameters (referral and promo codes, e-mails, phone numbers, codes) and the
 * secret parts of URL paths (password-reset tokens, e-mail verification hashes)
 * in request URLs, transaction names, spans and breadcrumbs.
 *
 * The lists are shared with the browser SDK: resources/js/sentry-private.json.
 */
class SentryScrubber
{
    public const FILTERED = '[Filtered]';

    /** @var array{queryParams: list<string>, secretPaths: list<string>}|null */
    private static ?array $lists = null;

    public static function beforeSend(Event $event): Event
    {
        self::scrubEvent($event);

        return $event;
    }

    public static function beforeSendTransaction(Event $event): Event
    {
        self::scrubEvent($event);

        foreach ($event->getSpans() as $span) {
            if (is_string($description = $span->getDescription())) {
                $span->setDescription(self::url($description));
            }

            $data = $span->getData();
            foreach (['url', 'http.url', 'http.query'] as $key) {
                if (isset($data[$key]) && is_string($data[$key])) {
                    $data[$key] = self::url($data[$key]);
                }
            }
            $span->setData($data);
        }

        return $event;
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        if (is_string($message = $breadcrumb->getMessage())) {
            $breadcrumb = $breadcrumb->withMessage(self::url($message));
        }

        foreach (['url', 'from', 'to', 'http.url'] as $key) {
            $value = $breadcrumb->getMetadata()[$key] ?? null;
            if (is_string($value)) {
                $breadcrumb = $breadcrumb->withMetadata($key, self::url($value));
            }
        }

        return $breadcrumb;
    }

    /**
     * Masks private query values and every path segment after a secret prefix.
     * Works on full URLs, paths and free text that contains them.
     */
    public static function url(string $value): string
    {
        $lists = self::lists();

        foreach ($lists['secretPaths'] as $prefix) {
            $value = (string) preg_replace(
                '~('.preg_quote($prefix, '~').')[^\s?#"\']+~',
                '$1'.self::FILTERED,
                $value,
            );
        }

        if (! str_contains($value, '=')) {
            return $value;
        }

        $terms = implode('|', array_map(fn (string $t) => preg_quote($t, '~'), $lists['queryParams']));

        return (string) preg_replace(
            '~([?&;][^=&\s#]*(?:'.$terms.')[^=&\s#]*=)[^&\s#"\']*~i',
            '$1'.self::FILTERED,
            $value,
        );
    }

    /** @return list<string> */
    public static function queryParams(): array
    {
        return self::lists()['queryParams'];
    }

    private static function scrubEvent(Event $event): void
    {
        $request = $event->getRequest();

        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = self::url($request['url']);
        }
        if (isset($request['query_string']) && is_string($request['query_string'])) {
            $request['query_string'] = ltrim(self::url('?'.$request['query_string']), '?');
        }
        $event->setRequest($request);

        if (is_string($transaction = $event->getTransaction())) {
            $event->setTransaction(self::url($transaction));
        }

        $event->setBreadcrumb(array_map(self::beforeBreadcrumb(...), $event->getBreadcrumbs()));
    }

    /** @return array{queryParams: list<string>, secretPaths: list<string>} */
    private static function lists(): array
    {
        return self::$lists ??= json_decode(
            (string) file_get_contents(resource_path('js/sentry-private.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
