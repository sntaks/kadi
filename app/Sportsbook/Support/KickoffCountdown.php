<?php

namespace App\Sportsbook\Support;

use Carbon\CarbonInterface;

/**
 * "Starts in 2 h 14 min" for a kick-off. The wording lives here only: the server renders the first value, so the card
 * reads right without JavaScript, and labels() hands the same templates to resources/js/sports/countdown.js, which
 * keeps it current in the browser.
 */
final class KickoffCountdown
{
    /**
     * The templates, translated. Placeholders :d, :h and :m are days, hours and minutes.
     *
     * @return array{kicked: string, now: string, minutes: string, hours: string, day: string, days: string}
     */
    public static function labels(): array
    {
        return [
            'kicked' => __('Kicked off'),
            'now' => __('Kicking off now'),
            'minutes' => __('Starts in :m min'),
            'hours' => __('Starts in :h h :m min'),
            'day' => __('Starts in :d day'),
            'days' => __('Starts in :d days'),
        ];
    }

    public static function label(CarbonInterface $kickoff, ?CarbonInterface $now = null): string
    {
        $seconds = $kickoff->getTimestamp() - ($now ?? now())->getTimestamp();
        $labels = self::labels();
        $days = intdiv(max($seconds, 0), 86400);

        $template = match (true) {
            $seconds <= 0 => $labels['kicked'],
            $seconds < 60 => $labels['now'],
            $seconds < 3600 => $labels['minutes'],
            $seconds < 86400 => $labels['hours'],
            default => $days === 1 ? $labels['day'] : $labels['days'],
        };

        return strtr($template, [':d' => (string) $days, ':h' => (string) intdiv(max($seconds, 0), 3600), ':m' => (string) intdiv(max($seconds, 0) % 3600, 60)]);
    }
}
