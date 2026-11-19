<?php

namespace App\Sportsbook\Support;

use Illuminate\Support\Str;

/**
 * Which country or region a competition belongs to, for the sport page picker (config sportsbook.regions). The Odds
 * API has no country field: a key listed in `competitions` wins, then the first word(s) of the key after the sport
 * prefix are matched against the countries (soccer_spain_la_liga → Spain). Anything else is International.
 */
final class CompetitionRegion
{
    public const FALLBACK = 'world';

    /** The country or region code of a feed key (soccer_epl → gb-eng). */
    public static function code(string $oddsApiKey): string
    {
        $config = (array) config('sportsbook.regions');

        if (isset($config['competitions'][$oddsApiKey])) {
            return (string) $config['competitions'][$oddsApiKey];
        }

        // Drop the sport prefix (soccer_, basketball_, icehockey_): the country, if any, comes next.
        $rest = Str::after($oddsApiKey, '_');

        foreach ((array) ($config['countries'] ?? []) as $code => $country) {
            $match = $country['match'] ?? Str::snake(Str::lower((string) $country['name']));

            if ($rest === $match || str_starts_with($rest, $match.'_')) {
                return (string) $code;
            }
        }

        return self::FALLBACK;
    }

    /** What players see for a code: "Spain", "Europe", "International". */
    public static function name(string $code): string
    {
        $config = (array) config('sportsbook.regions');

        return (string) ($config['regions'][$code] ?? $config['countries'][$code]['name'] ?? $config['regions'][self::FALLBACK] ?? 'International');
    }

    /** Regions come first, in their configured order; countries follow A–Z. */
    public static function isRegion(string $code): bool
    {
        return array_key_exists($code, (array) config('sportsbook.regions.regions'));
    }

    /** The flag's public path, or null when the code has none (International, Africa, South America: a globe). */
    public static function flag(string $code): ?string
    {
        $path = 'images/flags/'.$code.'.svg';

        return preg_match('/^[a-z-]+$/', $code) === 1 && is_file(public_path($path)) ? $path : null;
    }

    /**
     * A competition's name without its country, for rows that already show it: the feed's "Ligue 1 - France" and
     * "Brazil Série A" read "Ligue 1" and "Série A" under France and Brazil. A name that is only the country stays.
     */
    public static function shortName(string $name, string $code): string
    {
        $country = self::name($code);
        $short = trim((string) preg_replace('/^'.preg_quote($country, '/').'\s+|\s*[-–]\s*'.preg_quote($country, '/').'$/iu', '', $name));

        return $short === '' ? $name : $short;
    }

    /** Words the picker's search matches besides the competition and country names. */
    public static function aliases(string $oddsApiKey): string
    {
        return (string) (config('sportsbook.regions.aliases')[$oddsApiKey] ?? '');
    }

    /**
     * A sport's competitions grouped by country or region: regions first (configured order), then countries A–Z,
     * each with the real total of its competitions' open matches.
     *
     * @param  list<array{name: string, slug: string, count: int, region: string}>  $competitions
     * @return list<array{code: string, name: string, flag: ?string, count: int, competitions: list<array<string, mixed>>}>
     */
    public static function group(array $competitions): array
    {
        $groups = [];

        foreach ($competitions as $competition) {
            $code = $competition['region'];
            $groups[$code] ??= ['code' => $code, 'name' => self::name($code), 'flag' => self::flag($code), 'count' => 0, 'competitions' => []];
            $groups[$code]['count'] += $competition['count'];
            $groups[$code]['competitions'][] = $competition;
        }

        $regionOrder = array_flip(array_keys((array) config('sportsbook.regions.regions')));

        uasort($groups, fn (array $a, array $b): int => [! isset($regionOrder[$a['code']]), $regionOrder[$a['code']] ?? 0, $a['name']]
            <=> [! isset($regionOrder[$b['code']]), $regionOrder[$b['code']] ?? 0, $b['name']]);

        return array_values($groups);
    }
}
