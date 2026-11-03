{{-- Phone bottom bar (below 640px): Menu · Sports/Wallet · raised ♠ Kadi · Casino/Home · Bet slip/Account. --}}
@props(['nav'])
<nav @class(["nt-bottom", "nt-gated" => $nav->isGated()]) aria-label="Quick links" @if ($nav->isGated()) inert @endif>
    @foreach ($nav->bottomBar() as $item)
        @switch($item['kind'])
            @case('menu')
                <button type="button" aria-controls="site-menu" x-bind:aria-expanded="menuOpen" x-on:click="menuOpen = true">
                    <x-site.icon name="menu" />{{ $item['label'] }}
                </button>
                @break
            @case('kadi')
                @auth
                    <x-play-kadi class="nt-kadi-btn" aria-label="Play Kadi">
                        <span><img src="{{ asset('logo/kadi-card.png') }}" alt="" width="30" height="30"></span>{{ $item['label'] }}
                    </x-play-kadi>
                @else
                    <a href="{{ route('login') }}" class="nt-kadi-btn" aria-label="Play Kadi" data-needs-account>
                        <span><img src="{{ asset('logo/kadi-card.png') }}" alt="" width="30" height="30"></span>{{ $item['label'] }}
                    </a>
                @endauth
                @break
            @case('slip')
                {{-- Opens the persisted bet slip (layouts/site.blade.php) once the sports pages fill it (Phase 9). --}}
                <button type="button" aria-controls="bet-slip" x-on:click="$dispatch('open-bet-slip')"><x-site.icon name="slip" />{{ $item['label'] }}</button>
                @break
            @default
                <x-site.nav-item :item="$item" class="" />
        @endswitch
    @endforeach
</nav>
