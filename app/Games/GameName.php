<?php

namespace App\Games;

/**
 * One look for game and title names from the Kadi API, which sends them in mixed styles
 * ("BERRY BLAST", "Captain’s Chest"): the first letter of every word capitalised, the rest
 * lower case, single spaces. Letters after an apostrophe stay lower case ("Captain’s").
 */
final class GameName
{
    public static function format(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return (string) preg_replace_callback(
            '/(^|[\s\-\/(])(\p{L})/u',
            fn (array $m) => $m[1].mb_strtoupper($m[2]),
            mb_strtolower($name),
        );
    }

    /** A readable name from a product code when the catalogue has none: `slots_neon_city` → "Neon City". */
    public static function fromCode(string $game, string $code): string
    {
        return self::format(str_replace('_', ' ', GameTitle::keyFor($game, $code)));
    }
}
