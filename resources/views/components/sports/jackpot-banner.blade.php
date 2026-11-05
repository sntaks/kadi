{{-- The sports jackpot while it takes entries (SportsLobby::openJackpot()), on /sports. --}}
@props(['jackpot'])
<a {{ $attributes->class('nt-panel nt-jp-banner') }} href="{{ route('sports.jackpot') }}" wire:navigate>
    <span>
        <b>{{ $jackpot['name'] }}</b>
        <span class="nt-num">Predict {{ $jackpot['matches'] }} matches for {{ \App\Sportsbook\Support\Money::format($jackpot['entry_price']) }} · top prize {{ \App\Sportsbook\Support\Money::format($jackpot['top_prize']) }}</span>
    </span>
    <span class="nt-btn nt-btn-gold nt-btn-sm">Play the jackpot</span>
</a>
