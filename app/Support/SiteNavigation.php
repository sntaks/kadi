<?php

namespace App\Support;

use App\Models\User;
use App\Sportsbook\Catalogue\SportsLobby;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Support\Facades\Route;

/**
 * Every menu of the one site shell (plan §2 "Menu"): the game tabs, the side menu, the account
 * menu, the footer and the phone bottom bar. One list, so the menus never disagree.
 *
 * Guests see the same menu as players. A link that needs an account keeps its real URL: the `auth`
 * middleware sends a guest to log in and back to that page afterwards (`needsAccount` marks it for
 * the sign-in sheet in Phase 6b). Items whose page does not exist yet (casino, sports, promotions,
 * responsible play) appear when their route does, so each later phase only adds a route.
 *
 * An item: label, href (null for "Play Kadi", which is <x-play-kadi>), icon, active, needsAccount,
 * external, kind (`link` or `play-kadi`).
 */
final class SiteNavigation
{
    /** Routes that hold a signed-in player until they finish a step: the menu shows but is inert. */
    public const GATE_ROUTES = ['consent.show', 'name.edit'];

    public function __construct(private readonly ?User $user) {}

    public static function current(): self
    {
        $user = auth()->user();

        return new self($user instanceof User ? $user : null);
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function isGated(): bool
    {
        return request()->routeIs(...self::GATE_ROUTES);
    }

    /** Game tabs in the top bar, in the product's order: Sports · Kadi · Casino. @return list<array<string, mixed>> */
    public function products(): array
    {
        return array_values(array_filter([
            $this->sportsOn() ? $this->link('Sports', 'sports', 'ball', active: 'sports*') : null,
            $this->playKadi('Kadi'),
            $this->casinoOn() ? $this->link('Casino', 'casino', 'slot', active: 'casino') : null,
        ]));
    }

    /**
     * The collapsible groups of the side menu.
     *
     * @return list<array{key: string, label: string, icon: string, items: list<array<string, mixed>>}>
     */
    public function groups(): array
    {
        // Kadi · Casino · Sports, each collapsed until opened (the group of the current page starts open).
        $groups = [];

        $groups[] = ['key' => 'kadi', 'label' => 'Kadi', 'icon' => 'cards', 'items' => [
            $this->playKadi('Play Kadi'),
            $this->link('How to play', 'rules', 'cards'),
            $this->link('Games & prizes', 'game-guide', 'gift'),
        ]];

        if ($this->casinoOn()) {
            $groups[] = ['key' => 'casino', 'label' => 'Casino', 'icon' => 'slot', 'items' => [
                $this->casinoLink('All games', null),
                $this->casinoLink('Slots', 'slots'),
                $this->casinoLink('Scratch cards', 'scratch-cards'),
                $this->casinoLink('Jackpot slots', 'jackpot'),
                $this->casinoLink('Favourites', 'favourites'),
            ]];
        }

        if ($this->sportsOn()) {
            $groups[] = ['key' => 'sports', 'label' => 'Sports', 'icon' => 'ball', 'items' => $this->sportsItems()];
        }

        return $groups;
    }

    /** Flat account links under the groups. @return list<array<string, mixed>> */
    public function accountLinks(): array
    {
        return array_values(array_filter([
            $this->link('Wallet', 'wallet', 'wallet', needsAccount: true),
            $this->activityLink(needsAccount: true),
            Route::has('promotions') ? $this->link('Promotions', 'promotions', 'gift') : null,
            config('kadi.referrals.enabled') ? $this->link('Invite & Earn', 'referrals', 'invite', needsAccount: true) : null,
            $this->responsiblePlayOn() ? $this->link('Responsible play', 'responsible-play', 'shield', needsAccount: true) : null,
        ]));
    }

    /** @return list<array<string, mixed>> */
    public function helpLinks(): array
    {
        $whatsapp = Contact::whatsapp();

        return array_values(array_filter([
            $this->link('How to play', 'rules', 'cards'),
            $this->link('Games & prizes', 'game-guide', 'gift'),
            $this->link('FAQ', 'faq', 'help'),
            $whatsapp ? $this->item('WhatsApp', $whatsapp, 'chat', external: true) : null,
            $this->link('Terms', 'legal.terms', 'doc'),
            $this->link('Privacy', 'legal.privacy', 'doc'),
        ]));
    }

    /**
     * The account area's sub-tabs: the account page, wallet, activity, Invite & Earn and responsible play
     * share one header (plan Phase 6). @return list<array<string, mixed>>
     */
    public function accountArea(): array
    {
        return array_values(array_filter([
            $this->link('Account', $this->accountRoute(), 'user'),
            $this->link('Wallet', 'wallet', 'wallet'),
            $this->activityLink(),
            config('kadi.referrals.enabled') ? $this->link('Invite & Earn', 'referrals', 'invite') : null,
            $this->responsiblePlayOn() ? $this->link('Responsible play', 'responsible-play', 'shield') : null,
        ]));
    }

    /** The avatar menu (players only). Hash links open the matching profile tab. @return list<list<array<string, mixed>>> sections */
    public function accountMenu(): array
    {
        $profile = route($this->accountRoute());

        return [
            [
                $this->item('Profile', $profile.'#info', 'user'),
                $this->item('Security', $profile.'#security', 'key'),
                $this->item('Notifications', $profile.'#notifications', 'bell'),
            ],
            array_values(array_filter([
                $this->link('Wallet', 'wallet', 'wallet'),
                $this->activityLink(),
                config('kadi.referrals.enabled') ? $this->link('Invite & Earn', 'referrals', 'invite') : null,
                $this->responsiblePlayOn() ? $this->link('Responsible play', 'responsible-play', 'shield') : null,
            ])),
        ];
    }

    /**
     * The phone bottom bar: Menu · Sports · ♠ Kadi · Casino · Bet slip. Until sports is on, Sports
     * and Bet slip are Wallet and Account; until the casino page exists, Casino is Home.
     *
     * @return list<array<string, mixed>>
     */
    public function bottomBar(): array
    {
        $sports = $this->sportsOn();

        return [
            ['kind' => 'menu', 'label' => 'Menu', 'icon' => 'menu'],
            $sports ? $this->link('Sports', 'sports', 'ball', active: 'sports*') : $this->link('Wallet', 'wallet', 'wallet', needsAccount: true),
            ['kind' => 'kadi', 'label' => 'Kadi', 'icon' => 'cards'],
            $this->casinoOn() ? $this->link('Casino', 'casino', 'slot', active: 'casino') : $this->link('Home', 'home', 'home'),
            $sports ? ['kind' => 'slip', 'label' => 'Bet slip', 'icon' => 'slip'] : $this->link('Account', $this->accountRoute(), 'user', needsAccount: true),
        ];
    }

    /** Footer columns. @return array<string, list<array<string, mixed>>> */
    public function footer(): array
    {
        $deepLinks = (bool) config('platform.deep_link');

        return [
            'Play' => array_values(array_filter([
                ...$this->products(),
                $deepLinks ? $this->link('Slots', 'games.slots', 'slot') : null,
                $deepLinks ? $this->link('Scratch cards', 'games.scratch', 'slot') : null,
            ])),
            'Account' => $this->accountLinks(),
            'Help' => array_values(array_filter($this->helpLinks(), fn (array $item) => ! $item['external'])),
        ];
    }

    /** The account page (Phase 6). */
    public function accountRoute(): string
    {
        return 'account';
    }

    /** Activity: every game, Kadi's history as its own tab. @return array<string, mixed> */
    private function activityLink(bool $needsAccount = false): array
    {
        return $this->link('Activity', 'activity', 'list', needsAccount: $needsAccount);
    }

    private function responsiblePlayOn(): bool
    {
        return Route::has('responsible-play');
    }

    /** The casino browse page exists while games open inside the site. */
    private function casinoOn(): bool
    {
        return config('platform.token_launch') && Route::has('casino');
    }

    /** @return array<string, mixed> */
    private function casinoLink(string $label, ?string $filter): array
    {
        $item = $this->item($label, route('casino', array_filter(['filter' => $filter])), 'slot');
        $item['active'] = request()->routeIs('casino') && request()->route('filter') === $filter;

        return $item;
    }

    private function sportsOn(): bool
    {
        return SportsPages::on();
    }

    /**
     * The side menu's Sports group (plan §2): each sport with matches open (Football, Basketball) and its real count,
     * the sports jackpot while it takes entries, and In play (a list only, so marked Soon).
     *
     * @return list<array<string, mixed>>
     */
    private function sportsItems(): array
    {
        $lobby = app(SportsLobby::class);
        $current = $this->currentSport();
        $items = [];

        // One entry per sport with matches open (real counts); its leagues are in the sport page's own sidebar.
        foreach ($lobby->sports() as $sport) {
            $items[] = $this->item($sport['name'], SportsLobby::sportUrl($sport['slug']), $sport['icon'], active: $current === $sport['slug']) + ['count' => $sport['count']];
        }

        if ($lobby->openJackpot() !== null) {
            $items[] = $this->link('Sports jackpot', 'sports.jackpot', 'gift');
        }

        $items[] = $this->link('In play', 'sports.live', 'live') + ['badge' => 'Soon'];

        return $items;
    }

    /** The sport the current page belongs to: a sport page, or a competition page's sport. */
    private function currentSport(): ?string
    {
        $route = request()->route();

        return match (true) {
            request()->routeIs('sports') => 'football',
            request()->routeIs('sports.sport') => (string) $route?->parameter('sport'),
            request()->routeIs('sports.competition') => ($competition = $route?->parameter('competition')) instanceof Competition
                ? SportProfile::forSport($competition->sport)->slug
                : null,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function link(string $label, string $route, string $icon, bool $needsAccount = false, ?string $active = null): array
    {
        return $this->item($label, route($route), $icon, active: request()->routeIs($active ?? $route), needsAccount: $needsAccount && $this->isGuest()) + ['route' => $route];
    }

    /** @return array<string, mixed> */
    private function item(string $label, string $href, string $icon, bool $active = false, bool $needsAccount = false, bool $external = false): array
    {
        return compact('label', 'href', 'icon', 'active', 'needsAccount', 'external') + ['kind' => 'link'];
    }

    /** "Play Kadi" is <x-play-kadi> for players and a sign-in link for guests. @return array<string, mixed> */
    private function playKadi(string $label): array
    {
        if ($this->isGuest()) {
            return $this->item($label, route('login'), 'cards', needsAccount: true);
        }

        return ['kind' => 'play-kadi', 'label' => $label, 'href' => null, 'icon' => 'cards', 'active' => request()->routeIs('play') && request()->route('game') === 'kadi', 'needsAccount' => false, 'external' => false];
    }
}
