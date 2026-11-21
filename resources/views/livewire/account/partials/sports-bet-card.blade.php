{{-- One sports bet as a slip (Activity › Sports, and the bet slip's My bets with `expanded`), laid out like other
     sportsbooks' "My bets": status and why (App\Sportsbook\Support\BetOutcome), stake and result, then every pick as
     match → your pick → odds → score → its own status with a mark (✓ won, ✗ lost). A lost pick is highlighted, and
     picks that can no longer change a lost multibet say "Not needed" instead of looking live. Open bets start
     expanded; settled ones show the summary and open on a tap. Needs $bet with legs.fixture.result loaded.
     Void reasons are staff text, so a void pick only says how it counts. --}}
@php
    use App\Sportsbook\Enums\BetStatus;
    use App\Sportsbook\Support\BetOutcome;
    use App\Sportsbook\Support\Money;
    $tz = config('sportsbook.timezone');
    $outcome = new BetOutcome($bet);
    $tone = $outcome->tone;
    $startOpen = ($expanded ?? false) || in_array($bet->status, [BetStatus::Open, BetStatus::Pending], true);
    $marks = ['won' => 'check', 'lost' => 'x', 'void' => 'minus', 'moot' => 'minus', 'live' => 'live', 'open' => 'clock'];
@endphp
<li class="nt-bet" data-status="{{ $tone }}" wire:key="bet-{{ $bet->id }}" data-test="sports-bet">
    <details class="nt-bet-slip" wire:ignore.self @if ($startOpen) open @endif>
        <summary class="nt-bet-head">
            <span class="nt-bet-badge" data-status="{{ $tone }}">{{ $outcome->label }}</span>
            <span class="nt-bet-title">
                <b>{{ $outcome->isMulti() ? 'Multibet · '.$outcome->total.' picks' : 'Single' }}{{ $bet->isInPlay() ? ' · Live' : '' }}</b>
                <small class="nt-num">{{ $bet->placed_at->timezone($tz)->format('j M, H:i') }} · Bet ID {{ $bet->reference }}</small>
            </span>
            <span class="nt-bet-result nt-num">
                @if ($tone === 'lost')
                    <b class="nt-out">{{ $bet->isFreeBet() ? 'Lost (free bet)' : 'Lost '.Money::format($bet->stake) }}</b>
                    <small>Odds {{ $bet->total_odds }}</small>
                @else
                    @switch($bet->status)
                        @case(BetStatus::Won)
                            <b class="nt-in">Won {{ Money::format($bet->netPayout() ?? 0) }}</b>
                            <small>Stake {{ Money::format($bet->stake) }}</small>
                            @break
                        @case(BetStatus::Open)
                            <b>Stake {{ Money::format($bet->stake) }}</b>
                            <small>Pays {{ Money::format($bet->potential_payout) }}</small>
                            @break
                        @case(BetStatus::Pending)
                            <b>Stake {{ Money::format($bet->stake) }}</b>
                            <small>Checking…</small>
                            @break
                        @case(BetStatus::Rejected)
                            <b>Not placed</b>
                            <small>Nothing was taken</small>
                            @break
                        @default
                            <b>{{ $bet->payout ? Money::format($bet->payout).' back' : 'Stake back' }}</b>
                            <small>{{ $bet->status->label() }}</small>
                    @endswitch
                @endif
            </span>
            <x-site.icon name="chev" />
            @if ($outcome->reason !== '')
                <span class="nt-bet-reason" data-status="{{ $tone }}" data-test="bet-reason">{{ $outcome->reason }}</span>
            @endif
        </summary>

        <ol class="nt-bet-legs">
            @foreach ($bet->legs as $leg)
                @php
                    $fixture = $leg->fixture;
                    $result = $fixture?->result;
                    $live = $leg->status === \App\Sportsbook\Enums\BetLegStatus::Open && $result === null && $fixture !== null && $fixture->hasKickedOff();
                    $score = match (true) {
                        $result !== null && ! $result->is_void && $result->home_score !== null => 'FT '.$result->home_score.'-'.$result->away_score,
                        $live && $fixture->home_score !== null => $fixture->home_score.'-'.$fixture->away_score,
                        default => null,
                    };
                    $legTone = $outcome->legTone($leg, $live);
                @endphp
                <li class="nt-bet-leg" data-status="{{ $legTone }}" wire:key="bet-{{ $bet->id }}-leg-{{ $leg->id }}">
                    <span class="nt-bet-mark" data-status="{{ $legTone }}" aria-hidden="true"><x-site.icon :name="$marks[$legTone]" /></span>
                    <span class="nt-bet-leg-main">
                        <b>{{ $leg->fixture_name }}</b>
                        <small class="nt-num">{{ $leg->kicks_off_at?->timezone($tz)->format('D j M, H:i') }}</small>
                        <span class="nt-bet-pick">Your pick <b>{{ $leg->selection_name }}</b> · {{ $leg->market_name }}</span>
                    </span>
                    <span class="nt-bet-leg-end">
                        @if ($score)
                            <span @class(['nt-bet-score nt-num', 'nt-gold' => $live])>{{ $live ? 'Live '.$score : $score }}</span>
                        @elseif ($live)
                            <span class="nt-bet-score nt-gold">In play</span>
                        @endif
                        <span class="nt-bet-odds nt-num"><span class="sr-only">Odds </span>{{ $leg->price }}</span>
                        <span class="nt-bet-badge nt-bet-badge-sm" data-status="{{ $legTone }}">{{ $outcome->legLabel($leg, $live) }}</span>
                    </span>
                </li>
            @endforeach
        </ol>

        <dl class="nt-bet-sum nt-num">
            <div><dt>{{ $bet->isFreeBet() ? 'Free bet stake' : 'Stake' }}</dt><dd>{{ Money::format($bet->stake) }}</dd></div>
            <div><dt>Total odds</dt><dd>{{ $bet->total_odds }}</dd></div>
            @if ($bet->bonus_percent > 0)
                <div><dt>Multibet bonus</dt><dd>+{{ $bet->bonus_percent }}%</dd></div>
            @endif
            @if ($tone === 'lost')
                <div class="nt-bet-total"><dt>You lost</dt><dd class="nt-out">{{ $bet->isFreeBet() ? 'Free bet, no cash' : Money::format($bet->stake) }}</dd></div>
            @else
                @switch($bet->status)
                    @case(BetStatus::Won)
                        <div><dt>Paid</dt><dd>{{ Money::format($bet->payout ?? 0) }}</dd></div>
                        @if ($bet->tax)<div><dt>Winnings tax</dt><dd>−{{ Money::format($bet->tax) }}</dd></div>@endif
                        <div class="nt-bet-total"><dt>You won</dt><dd class="nt-in">{{ Money::format($bet->netPayout() ?? 0) }}</dd></div>
                        @break
                    @case(BetStatus::Open)
                    @case(BetStatus::Pending)
                        <div class="nt-bet-total"><dt>Possible payout (before tax)</dt><dd class="nt-gold">{{ Money::format($bet->potential_payout) }}</dd></div>
                        @break
                    @case(BetStatus::Rejected)
                        <div class="nt-bet-total"><dt>Not placed</dt><dd>Nothing was taken from your wallet</dd></div>
                        @break
                    @default
                        <div class="nt-bet-total"><dt>Returned</dt><dd>{{ Money::format($bet->payout ?? 0) }}</dd></div>
                @endswitch
            @endif
        </dl>
    </details>
</li>
