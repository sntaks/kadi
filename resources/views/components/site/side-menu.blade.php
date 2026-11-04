{{--
    Side menu: sticky on desktop, a drawer below 1024px (opened by the top bar or the bottom bar's Menu).
    Collapsible game groups (Kadi, Casino, Sports; collapsed until opened), then flat account links, then Help, then 18+. Rules, not boxes.
--}}
@props(['nav'])
@php($user = auth()->user())
<nav id="site-menu" aria-label="Main"
     @class(['nt-side', 'nt-gated' => $nav->isGated()]) x-bind:class="menuOpen && 'nt-open'"
     x-trap.inert.noscroll="menuOpen"
     x-on:keydown.escape="menuOpen = false"
     @if ($nav->isGated()) inert @endif>
    <div class="mb-2 flex justify-end lg:hidden">
        <button type="button" class="nt-icon-btn" aria-label="Close menu" x-on:click="menuOpen = false"><x-site.icon name="x" /></button>
    </div>

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
            <button type="button" aria-expanded="{{ $open ? 'true' : 'false' }}" x-bind:aria-expanded="open" aria-controls="site-group-{{ $group['key'] }}" x-on:click="open = ! open">
                <x-site.icon :name="$group['icon']" />{{ $group['label'] }}<x-site.icon name="chev" class="nt-chev" />
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
            <x-site.nav-item :item="$item" />
        @endforeach
        @if ($user)
            <x-pwa.install-button variant="shell" class="lg:hidden" />
        @endif
    </div>

    <div class="nt-links">
        <h2>Help</h2>
        @foreach ($nav->helpLinks() as $item)
            <x-site.nav-item :item="$item" />
        @endforeach
    </div>

    <p class="nt-age">18+ only. Play responsibly.</p>
</nav>
<div class="nt-scrim lg:hidden" x-show="menuOpen" x-cloak x-transition.opacity x-on:click="menuOpen = false"></div>
