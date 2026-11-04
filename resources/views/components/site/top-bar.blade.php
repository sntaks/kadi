{{-- Top bar: menu button (drawer sizes), logo, game tabs, search, wallet pill or Log in · Join, bell, account. --}}
@props(['nav'])
@php($user = auth()->user())
<header class="nt-top">
    @unless ($nav->isGated())
        <button type="button" class="nt-icon-btn lg:hidden" aria-label="Open menu" aria-controls="site-menu"
                x-bind:aria-expanded="menuOpen" x-on:click="menuOpen = true">
            <x-site.icon name="menu" />
        </button>
    @endunless

    <a href="{{ route('home') }}" class="nt-logo" aria-label="Kadi home" wire:navigate>
        <img src="{{ asset('logo/kadi-logo.webp') }}" alt="" width="228" height="96">
    </a>

    <nav @class(['nt-products', 'nt-gated' => $nav->isGated()]) aria-label="Games" @if ($nav->isGated()) inert @endif>
        @foreach ($nav->products() as $item)
            <x-site.nav-item :item="$item" class="" />
        @endforeach
    </nav>

    @unless ($nav->isGated())
        <x-site.search />
    @endunless

    <div class="nt-top-right">
        @if ($user)
            @unless ($nav->isGated())
                <livewire:wallet-balance variant="pill" wire:key="site-wallet" />
                <livewire:notifications-bell variant="shell" wire:key="site-bell" />
            @endunless
            <x-site.account-menu :nav="$nav" />
        @else
            <div class="nt-auth flex items-center gap-2">
                <x-ui.button variant="ghost" :href="route('login')" data-signin="login">Log in</x-ui.button>
                @if (Route::has('register'))
                    <x-ui.button :href="route('register')" data-signin="join">Join</x-ui.button>
                @endif
            </div>
        @endif
    </div>
</header>
