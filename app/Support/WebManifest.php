<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * The installed app's manifest (/manifest.webmanifest, docs/pwa-push.md). Built per request so its shortcuts (plan
 * Phase 11: Kadi, Sports, Slots, Wallet) only point at pages that are switched on: a shortcut into a switched-off
 * page would open a 404 from the home screen. The `id` never changes, or browsers would see a different app.
 */
class WebManifest
{
    public const ID = '/';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => self::ID,
            'name' => 'Kadi',
            'short_name' => 'Kadi',
            'description' => 'Play Kadi, slots, scratch cards and football in Kenya, with one wallet.',
            // There is no dashboard: the lobby is home for everyone.
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#0a0a0a',
            'theme_color' => '#0e0d0b',
            'lang' => 'en',
            'categories' => ['games', 'entertainment'],
            'icons' => [
                ['src' => '/pwa-icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa-icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa-icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => $this->shortcuts(),
        ];
    }

    /**
     * Long-press shortcuts on the home-screen icon, in the plan's order. Account pages keep their real URL: the
     * `auth` redirect brings a signed-out player back after signing in.
     *
     * @return list<array<string, mixed>>
     */
    private function shortcuts(): array
    {
        $tokenLaunch = (bool) config('platform.token_launch');

        $slots = match (true) {
            (bool) config('platform.deep_link') && Route::has('games.slots') => route('games.slots', absolute: false),
            $tokenLaunch && Route::has('casino') => route('casino', ['filter' => 'slots'], absolute: false),
            default => null,
        };

        return array_values(array_filter([
            $this->shortcut('Play Kadi', 'Kadi', 'Sit down at a Kadi table', $tokenLaunch && Route::has('play') ? route('play', ['game' => 'kadi'], absolute: false) : '/', 'kadi'),
            SportsPages::on() ? $this->shortcut('Sports', 'Sports', 'Football matches and the bet slip', route('sports', absolute: false), 'sports') : null,
            $slots === null ? null : $this->shortcut('Slots', 'Slots', 'Every slot', $slots, 'slots'),
            $this->shortcut('Wallet', 'Wallet', 'Deposit, withdraw and your balance', route('wallet', absolute: false), 'wallet'),
        ]));
    }

    /** @return array<string, mixed> */
    private function shortcut(string $name, string $short, string $description, string $url, string $icon): array
    {
        return [
            'name' => $name,
            'short_name' => $short,
            'description' => $description,
            'url' => $url.(str_contains($url, '?') ? '&' : '?').'source=pwa-shortcut',
            'icons' => [['src' => "/pwa-icons/shortcut-{$icon}.png", 'sizes' => '192x192', 'type' => 'image/png']],
        ];
    }
}
