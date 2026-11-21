{{-- One sports bet as a slip (Activity › Sports): overall status, stake, odds and what it paid or lost, then every
     pick with its match, odds, score and own status. Open bets start expanded; settled ones show the summary and
     open on a tap. Needs $bet with legs.fixture.result loaded (App\Livewire\Account\Activity::sportsPage()).
     Void reasons are staff text, so a void pick only says how it counts. --}}
@php
    use App\Sportsbook\Enums\BetLegStatus;
    use App\Sportsbook\Enums\BetStatus;
    use App\Sportsbook\Support\Money;
    $tz = config('sportsbook.timezone');
    $tone = match ($bet->status) {
        BetStatus::Won => 'won',
        BetStatus::Lost => 'lost',
        BetStatus::Open, BetStatus::Pending => 'open',
        default => 'void',
    };
    $legCount = $bet->legs->count();
    $openBet = in_array($bet->status, [BetStatus::Open, BetStatus::Pending], true);
@endphp
<li class="nt-bet" data-status="{{ $tone }}" wire:key="bet-{{ $bet->id }}" data-test="sports-bet">
    <details class="nt-bet-slip" wire:ignore.self @if ($openBet) open @endif>
        <summary class="nt-bet-head">
            <span class="nt-bet-badge" data-status="{{ $tone }}">{{ $bet->status->label() }}</span>
            <span class="nt-bet-title">
                <b>{{ $legCount > 1 ? 'Multibet · '.$legCount.' picks' : 'Single' }}{{ $bet->isInPlay() ? ' · Live' : '' }}</b>
                <small class="nt-num">{{ $bet->placed_at->timezone($tz)->format('j M, H:i') }} · Bet ID {{ $bet->reference }}</small>
            </span>
            <span class="nt-bet-result nt-num">
                @switch($bet->status)
                    @case(BetStatus::Won)
                        <b class="nt-in">Won {{ Money::format($bet->netPayout() ?? 0) }}</b>
                        <small>Stake {{ Money::format($bet->stake) }}</small>
                        @break
                    @case(BetStatus::Lost)
                        <b class="nt-out">{{ $bet->isFreeBet() ? 'Lost (free bet)' : 'Lost '.Money::format($bet->stake) }}</b>
                        <small>Odds {{ $bet->total_odds }}</small>
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
            </span>
            <x-site.icon name="chev" />
        </summary>

        <ol class="nt-bet-legs">
            @foreach ($bet->legs as $leg)
                @php
                    $fixture = $leg->fixture;
                    $result = $fixture?->result;
                    $live = $leg->status === BetLegStatus::Open && $result === null && $fixture !== null && $fixture->hasKickedOff();
                    $score = match (true) {
                        $result !== null && ! $result->is_void && $result->home_score !== null => 'FT '.$result->home_score.'-'.$result->away_score,
                        $live && $fixture->home_score !== null => $fixture->home_score.'-'.$fixture->away_score,
                        default => null,
                    };
                    $legTone = match ($leg->status) {
                        BetLegStatus::Won => 'won',
                        BetLegStatus::Lost => 'lost',
                        BetLegStatus::Void => 'void',
                        BetLegStatus::Open => 'open',
                    };
                @endphp
                <li class="nt-bet-leg" wire:key="bet-{{ $bet->id }}-leg-{{ $leg->id }}">
                    <span class="nt-bet-dot" data-status="{{ $legTone }}" aria-hidden="true"></span>
                    <span class="nt-bet-leg-main">
                        <b>{{ $leg->selection_name }}</b>
                        <small>{{ $leg->market_name }}</small>
                        <small>{{ $leg->fixture_name }}</small>
                        <small class="nt-num">
                            {{ $leg->kicks_off_at?->timezone($tz)->format('D j M, H:i') }}
                            @if ($score) · <span class="{{ $live ? 'nt-gold' : '' }}">{{ $live ? 'Live '.$score : $score }}</span>@elseif ($live) · <span class="nt-gold">In play</span>@endif
                        </small>
                    </span>
                    <span class="nt-bet-leg-end">
                        <b class="nt-num">{{ $leg->price }}</b>
                        <span class="nt-bet-badge nt-bet-badge-sm" data-status="{{ $legTone }}">{{ $leg->status === BetLegStatus::Void ? 'Void · odds 1.00' : $leg->status->label() }}</span>
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
            @switch($bet->status)
                @case(BetStatus::Won)
                    <div><dt>Paid</dt><dd>{{ Money::format($bet->payout ?? 0) }}</dd></div>
                    @if ($bet->tax)<div><dt>Winnings tax</dt><dd>−{{ Money::format($bet->tax) }}</dd></div>@endif
                    <div class="nt-bet-total"><dt>You won</dt><dd class="nt-in">{{ Money::format($bet->netPayout() ?? 0) }}</dd></div>
                    @break
                @case(BetStatus::Lost)
                    <div class="nt-bet-total"><dt>You lost</dt><dd class="nt-out">{{ $bet->isFreeBet() ? 'Free bet, no cash' : Money::format($bet->stake) }}</dd></div>
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
        </dl>
    </details>
</li>
