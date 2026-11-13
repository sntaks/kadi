{{--
    Where a player picks a side: both sides, then what stops them (sign in, betting not open) and how the odds work.
    The Phase 4 bet slip takes over below the sides.
--}}
@props(['market', 'now', 'canBet' => false, 'bettingOn' => false, 'question' => true])
<section {{ $attributes->class('nt-panel nt-pm-pick') }} data-predict-market="{{ $market->id }}" aria-label="Predict">
    @if ($question)
        <small class="nt-pm-pick-label">Predict</small>
        <p class="nt-pm-pick-q">{{ $market->question }}</p>
    @endif
    <div class="nt-pm-sides nt-pm-sides-lg">
        <x-predict.outcome-button :$market side="yes" :$now as="button" :$canBet />
        <x-predict.outcome-button :$market side="no" :$now as="button" :$canBet />
    </div>
    @guest
        {{-- Opens the sign-in sheet (signin.js); in the prediction sheet, that sheet closes first so the two never stack. --}}
        <x-ui.button :href="route('login')" block data-needs-account x-on:click="$dispatch('predict-panel-handoff')">Sign in to predict</x-ui.button>
    @else
        @unless ($canBet)
            <p class="nt-pm-note">{{ $bettingOn ? 'Betting is not available on your account right now.' : 'Betting on predictions opens soon.' }}</p>
        @endunless
    @endguest
    <p class="nt-pm-explain">
        @if ($market->usesQuote())
            The multiplier is indicative. When you bet, you get a fixed price for 10 seconds, and that is what your bet pays.
        @else
            This is a pool: the multiplier is an estimate and moves as people bet. Your payout is fixed when the market closes.
        @endif
    </p>
</section>
