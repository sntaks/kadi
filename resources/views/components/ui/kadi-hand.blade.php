{{--
    The K-A-D-I hand: four ivory cards in Cinzel with SVG suits (navy and red), dealt in on load and
    fanned wider when the surrounding `.nt-hand-host` is hovered. Decorative: hidden from screen readers.
--}}
@php
    $suits = [
        'spade' => 'M12 2C9 6 4 9 4 13.5A4.5 4.5 0 0 0 11 17.2c-.3 1.9-1.2 3.3-2.5 4.3h7c-1.3-1-2.2-2.4-2.5-4.3A4.5 4.5 0 0 0 20 13.5C20 9 15 6 12 2z',
        'heart' => 'M12 21s-8-5.2-8-11.2A4.6 4.6 0 0 1 12 7a4.6 4.6 0 0 1 8 2.8C20 15.8 12 21 12 21z',
        'diamond' => 'M12 2 19 12 12 22 5 12z',
        'club' => 'M12 2.5a4.2 4.2 0 0 1 3.6 6.4 4.2 4.2 0 1 1-2.6 6.9c.3 2.2 1.2 3.9 2.5 5.2h-7c1.3-1.3 2.2-3 2.5-5.2a4.2 4.2 0 1 1-2.6-6.9A4.2 4.2 0 0 1 12 2.5z',
    ];
    $hand = [['K', 'spade', false], ['A', 'heart', true], ['D', 'diamond', true], ['I', 'club', false]];
@endphp
<div aria-hidden="true" {{ $attributes->class('nt-hand') }}>
    @foreach ($hand as [$rank, $suit, $red])
        <div @class(['nt-card', 'nt-card-red' => $red])>
            <span class="nt-pip">{{ $rank }}<svg viewBox="0 0 24 24"><path fill="currentColor" d="{{ $suits[$suit] }}"/></svg></span>
            <span class="nt-rank">{{ $rank }}</span>
            <span class="nt-pip nt-pip-end">{{ $rank }}<svg viewBox="0 0 24 24"><path fill="currentColor" d="{{ $suits[$suit] }}"/></svg></span>
        </div>
    @endforeach
</div>
