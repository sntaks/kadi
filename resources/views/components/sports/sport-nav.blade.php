{{-- The sport sidebar (like bcgame.ke's): the sports, then this sport's Highlights · Today and its leagues with real
     counts, popular leagues first. It sits beside the list when the page has room (container query in sports.css),
     and is a chip row above it otherwise. `active` is the competition slug on a competition page. --}}
@props(['sport', 'sports' => [], 'competitions' => [], 'active' => null, 'tab' => null])
@php
    $home = \App\Sportsbook\Catalogue\SportsLobby::sportUrl($sport);
    $onSport = $active === null;
    $popular = array_values(array_filter($competitions, fn (array $c) => $c['popular']));
    $others = array_values(array_filter($competitions, fn (array $c) => ! $c['popular']));
    $current = fn (bool $on) => $on ? 'aria-current=page' : '';
@endphp
<nav {{ $attributes->class('nt-sportnav') }} aria-label="Sports and leagues">
    @if (count($sports) > 1)
        <div class="nt-sportnav-sports">
            @foreach ($sports as $item)
                <a class="nt-sportnav-sport" href="{{ \App\Sportsbook\Catalogue\SportsLobby::sportUrl($item['slug']) }}" wire:navigate
                   @if ($item['slug'] === $sport) aria-current="{{ $onSport ? 'page' : 'true' }}" @endif>
                    <x-site.icon :name="$item['icon']" /><span>{{ $item['name'] }}</span><small class="nt-num">{{ $item['count'] }}</small>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Wide: a sidebar list. --}}
    <div class="nt-sportnav-list">
        <a href="{{ $home }}" wire:navigate {{ $current($onSport && ($tab === null || $tab === 'highlights')) }}><x-site.icon name="ball" />Highlights</a>
        <a href="{{ $home }}?tab=today" wire:navigate {{ $current($onSport && $tab === 'today') }}><x-site.icon name="clock" />Today</a>

        @if ($popular !== [])
            <h3>Popular</h3>
            @foreach ($popular as $competition)
                <a href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate {{ $current($active === $competition['slug']) }}>
                    <x-site.icon name="trophy" /><span>{{ $competition['name'] }}</span><small class="nt-num">{{ $competition['count'] }}</small>
                </a>
            @endforeach
        @endif

        @if ($others !== [])
            <h3>{{ $popular === [] ? 'Leagues' : 'All leagues' }}</h3>
            @foreach ($others as $competition)
                <a href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate {{ $current($active === $competition['slug']) }}>
                    <x-site.icon name="trophy" /><span>{{ $competition['name'] }}</span><small class="nt-num">{{ $competition['count'] }}</small>
                </a>
            @endforeach
        @endif
    </div>

    {{-- Narrow: the same leagues as chips. --}}
    @if ($competitions !== [])
        <div class="nt-chips nt-comp-chips nt-sportnav-chips">
            @foreach ([...$popular, ...$others] as $competition)
                <a class="nt-chip" href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate
                   @if ($active === $competition['slug']) aria-selected="true" @endif>{{ $competition['name'] }} <span class="nt-num">{{ $competition['count'] }}</span></a>
            @endforeach
        </div>
    @endif
</nav>
