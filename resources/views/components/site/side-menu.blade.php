{{--
    Side menu: full and sticky on desktop (collapsible to an icon rail), an icon rail on tablets (768–1239px), a drawer
    on phones; on tablets Menu or a rail group opens the full drawer over the page (resources/js/site/side-rail.js).
    Collapsible game groups (Kadi, Casino, Sports; collapsed until opened), then flat account links, then Help, then 18+. Rules, not boxes.
--}}
@props(['nav'])
@php($user = auth()->user())
<nav id="site-menu" aria-label="Main"
     @class(['nt-side', 'nt-gated' => $nav->isGated()]) x-bind:class="menuOpen && 'nt-open'"
     x-trap.inert.noscroll="menuOpen"
     x-on:keydown.escape="menuOpen = false"
     @if ($nav->isGated()) inert @endif>
    <div class="nt-side-close mb-2 flex justify-end min-[1240px]:hidden">
        <button type="button" class="nt-icon-btn" aria-label="Close menu" x-on:click="menuOpen = false"><x-site.icon name="x" /></button>
    </div>

    {{-- Desktop only: collapse the menu to the icon rail, or bring it back. --}}
    <button type="button" class="nt-rail-toggle" data-test="rail-toggle" aria-controls="site-menu"
            aria-label="Collapse menu" x-bind:aria-label="collapsed ? 'Expand menu' : 'Collapse menu'"
            aria-expanded="true" x-bind:aria-expanded="(! collapsed).toString()" x-on:click="toggleRail()">
        <x-site.icon name="chev" class="nt-rail-chev" /><span class="nt-label">Collapse menu</span>
    </button>

    @if ($user)
        <div class="nt-side-acct">
            <div class="nt-who"><div><b>{{ $user->name }}</b><span>{{ $user->email }}</span></div></div>
            <div class="grid grid-cols-2 gap-1.5">
                <x-ui.button variant="ghost" :href="route($nav->accountRoute())" wire:navigate>Account</x-ui.button>
                <form method="POST" action="{{ route('logout') }}" class="contents">
                    @csrf
                    <x-ui.button variant="ghost" type="submit">Log out</x-ui.button>
                </form>
            </div>
        </div>
    @endif

    @foreach ($nav->groups() as $group)
        {{-- Collapsed until opened; the group holding the current page starts open so its link stays in view. --}}
        @php($open = collect($group['items'])->contains(fn (array $item) => $item['active']))
        <div class="nt-group" x-data="{ open: @js($open) }">
            {{-- On the rail a group has no room for its links: tapping it brings the full menu, with the group open. --}}
            <button type="button" aria-expanded="{{ $open ? 'true' : 'false' }}" x-bind:aria-expanded="open" aria-controls="site-group-{{ $group['key'] }}"
                    x-bind:title="railed && ! menuOpen ? @js($group['label']) : null"
                    x-on:click="if (railed && ! menuOpen) { open = true; expand() } else { open = ! open }">
                <x-site.icon :name="$group['icon']" /><span class="nt-label">{{ $group['label'] }}</span><x-site.icon name="chev" class="nt-chev" />
            </button>
            <ul id="site-group-{{ $group['key'] }}" x-show="open" x-collapse @unless ($open) x-cloak @endunless>
                @foreach ($group['items'] as $item)
                    <li><x-site.nav-item :item="$item" /></li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <div class="nt-links">
        @foreach ($nav->accountLinks() as $item)
            <x-site.nav-item :item="$item" rail />
        @endforeach
        @if ($user)
            <x-pwa.install-button variant="shell" class="lg:hidden" />
        @endif
    </div>

    <div class="nt-links">
        <h2>Help</h2>
        @foreach ($nav->helpLinks() as $item)
            <x-site.nav-item :item="$item" rail />
        @endforeach
    </div>

    <p class="nt-age">18+ only. Play responsibly.</p>
</nav>
<div class="nt-scrim min-[1240px]:hidden" x-show="menuOpen" x-cloak x-transition.opacity x-on:click="menuOpen = false"></div>
