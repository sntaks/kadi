<?php

namespace App\Support;

/**
 * Sign-in and gate pages: the new look's panels (resources/views/auth) behind PLATFORM_UI_V2, the old
 * pages (resources/views/pages/auth) until then. Deleted with the old pages in Phase 12.
 */
final class AuthView
{
    public static function name(string $page): string
    {
        return config('platform.ui_v2') ? "auth.{$page}" : "pages::auth.{$page}";
    }
}
