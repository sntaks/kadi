{{-- One bet in the bet slip's My bets list: its picks, how it stands (App\Sportsbook\Support\BetOutcome, the same
     wording as the full card), then stake and payout or result. A tap opens the full slip in place (showBet in the
     slip host's x-data). Needs $bet with legs loaded. --}}
@php
    use App\Sportsbook\Enums\BetStatus;
    use App\Sportsbook\Support\BetOutcome;
    use App\Sportsbook\Support\Money;
    $outcome = new BetOutcome($bet);
    $picks = $bet->legs->pluck('selection_name');
    $summary = $picks->take(3)->implode(', ').($picks->count() > 3 ? ' +'.($picks->count() - 3) : '');
    $status = match (true) {
        $outcome->tone === 'lost' => $outcome->isMulti() ? $outcome->lost.' of '.$outcome->total.' picks lost' : 'Single',
        $bet->status === BetStatus::Pending => 'Checking…',
        $bet->status === BetStatus::Open && $outcome->isMulti() => $outcome->won.' of '.$outcome->total.' won, '.$outcome->open.' to play',
        default => $outcome->isMulti() ? 'Multibet, '.$outcome->total.' picks' : 'Single',
    };
@endphp
<button type="button" class="nt-open-bet" id="slip-bet-{{ $bet->id }}-row" wire:key="open-bet-{{ $bet->id }}" data-status="{{ $outcome->tone }}"
        x-on:click="showBet({{ $bet->id }})" aria-label="Show bet: {{ $summary }}, {{ $outcome->label }}" data-test="slip-bet-row">
    <span>{{ $summary }}<small>{{ $status }}</small></span>
    <span class="nt-num">
        @if ($outcome->tone === 'lost')
            <span class="nt-bet-badge nt-bet-badge-sm" data-status="lost">Lost</span><small>Stake {{ Money::format($bet->stake) }}</small>
        @else
            @switch($bet->status)
                @case(BetStatus::Won)
                    <b class="nt-in">Won {{ Money::format($bet->netPayout() ?? 0) }}</b><small>Stake {{ Money::format($bet->stake) }}</small>
                    @break
                @case(BetStatus::Open)
                @case(BetStatus::Pending)
                    {{ Money::format($bet->stake) }}<small>pays {{ Money::format($bet->potential_payout) }}</small>
                    @break
                @default
                    <span class="nt-bet-badge nt-bet-badge-sm" data-status="{{ $outcome->tone }}">{{ $outcome->label }}</span><small>{{ $bet->status === BetStatus::Rejected ? 'Nothing taken' : 'Stake back' }}</small>
            @endswitch
        @endif
    </span>
    <x-site.icon name="chev" />
</button>
