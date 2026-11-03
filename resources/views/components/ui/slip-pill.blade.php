{{-- Floating bet slip pill for phones: selections count and possible payout. Opens the slip sheet. --}}
@props(['count' => 0, 'payout' => null, 'sheet' => 'bet-slip'])
<button type="button" x-data x-on:click="$dispatch('open-sheet', @js($sheet))"
        aria-label="Bet slip, {{ $count }} {{ \Illuminate\Support\Str::plural('selection', $count) }}{{ $payout ? ', possible payout '.$payout : '' }}"
        {{ $attributes->class('nt-slip-pill') }}>
    <span>Bet slip</span>
    @if ($payout)<span class="nt-payout nt-num">{{ $payout }}</span>@endif
    <span class="nt-count nt-num" aria-hidden="true">{{ $count }}</span>
</button>
