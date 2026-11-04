{{-- One market of a match, with every selection as a price. --}}
@props(['market', 'fixture'])
@php
    $open = $market->isOpen() && ! $fixture->hasKickedOff();
    $columns = match (true) {
        $market->type === \App\Sportsbook\Enums\MarketType::CorrectScore => 'nt-mkt-grid-5',
        $market->selections->count() === 2 => 'nt-mkt-grid-2',
        default => 'nt-mkt-grid-3',
    };
@endphp
<section class="nt-mkt" data-match="{{ $fixture->name() }}" data-market="{{ $market->name() }}" wire:key="market-{{ $market->id }}">
    <header>
        <h3>{{ $market->name() }}</h3>
        @unless ($open)<span class="nt-pill">Suspended</span>@endunless
    </header>
    <div class="nt-mkt-grid {{ $columns }}">
        @foreach ($market->selections as $selection)
            <x-sports.odds :$selection :$fixture :open="$open" :label="$selection->name" wire:key="selection-{{ $selection->id }}" />
        @endforeach
    </div>
</section>
