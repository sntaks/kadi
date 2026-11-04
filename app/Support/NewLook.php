<?php

namespace App\Support;

/**
 * Picks the new-look view behind PLATFORM_UI_V2 and the old one until then, in one place, so Phase 12
 * deletes the old views by removing this switch.
 */
final class NewLook
{
    public static function view(string $new, string $old): string
    {
        return config('platform.ui_v2') ? $new : $old;
    }
}
