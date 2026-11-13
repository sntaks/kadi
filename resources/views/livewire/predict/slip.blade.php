{{--
    The prediction bet slip (App\Livewire\Predict\Slip). Guests get sign-in links (the prediction sheet hands over to
    the sign-in sheet). Players pick a side, type a stake and see the estimate; fixed-odds markets then get a price
    held 10 seconds (counted down from the server's figure, never the browser clock) and place it; pool markets
    place directly. live.js keeps the sides' multipliers current and tells the slip when the market stops taking
    bets.
--}}
@php
    $label = fn (string $side) => $market?->odds?->label($side) ?? ucfirst($side);
    $usesQuote = $market?->usesQuote() && ! $poolFlow;
    $retryReady = $retryIn === 0;
@endphp
<section class="nt-panel nt-pm-pick nt-pm-slip" data-predict-market="{{ $marketId }}" aria-label="Predict"
         x-data="{ closesIn: @js($closesIn) }"
         x-init="if (closesIn !== null && closesIn > 0) setTimeout(() => $wire.marketChanged(), closesIn * 1000)"
         x-on:predict-market-live.window="if ($event.detail.id === {{ $marketId }} && ($event.detail.status !== 'open' || ! $event.detail.betting_available)) $wire.marketChanged()">
    @if ($market === null)
        <p class="nt-pm-note">This market is not available right now.</p>
    @else
        @if ($showQuestion)
            <small class="nt-pm-pick-label">Predict</small>
            <p class="nt-pm-pick-q">{{ $market->question }}</p>
        @endif
        {{-- Sides --}}
        <div class="nt-pm-sides nt-pm-sides-lg" role="group" aria-label="Pick a side">
            @foreach (\App\Predict\Data\Market::SIDES as $s)
                @php
                    $multiplier = $market->odds?->multiplier($s);
                    $open = $market->sideAvailable($s, $now);
                @endphp
                @guest
                    <x-predict.outcome-button :$market :side="$s" :$now as="button" />
                @else
                    @php($below = $market->odds?->paysBelowStake($s) ?? false)
                    <button type="button" class="nt-pm-side nt-pm-side-{{ $s }}" data-predict-side="{{ $s }}" data-can-bet="{{ $canBet ? '1' : '0' }}" data-below-text="{{ __('Pays less than your stake') }}"
                            @if ($below) aria-label="{{ $label($s) }}: {{ __('pays less than your stake') }}" @endif
                            aria-pressed="{{ $side === $s ? 'true' : 'false' }}" @disabled(! $open || ! $canBet || $state === 'checking')
                            wire:click="pick('{{ $s }}')">
                        <span class="nt-pm-side-label">{{ $label($s) }}</span>
                        <span class="nt-pm-side-mult nt-num" data-predict-mult>{{ $multiplier !== null ? \App\Predict\Format::multiplier($multiplier) : ($below ? __('Pays less than your stake') : '–') }}</span>
                    </button>
                @endguest
            @endforeach
        </div>

        @guest
            <x-ui.button :href="route('login')" block data-needs-account x-on:click="$dispatch('predict-panel-handoff')">Sign in to predict</x-ui.button>
        @else
            @if ($blocked)
                <p class="nt-pm-note">{{ $blocked }}</p>
            @elseif ($state === 'placed' && $placed)
                <div class="nt-pm-done" role="status">
                    <b>Bet placed</b>
                    <p class="nt-num">
                        {{ \App\Predict\Format::kes($placed['stake']) }} on {{ $label($placed['side']) }}
                        @if ($placed['multiplier'])
                            at {{ \App\Predict\Format::multiplier($placed['multiplier']) }} ({{ $placed['estimate'] ? 'estimated' : 'fixed' }})
                        @endif
                    </p>
                    @if ($placed['payout'])
                        <p class="nt-num">{{ $placed['estimate'] ? 'Estimated payout' : 'Pays' }} {{ \App\Predict\Format::kes($placed['payout']) }} if {{ $label($placed['side']) }} wins.</p>
                    @endif
                    <x-ui.button variant="ghost" block wire:click="another">Place another bet</x-ui.button>
                </div>
            @elseif ($state === 'checking')
                <div class="nt-pm-checking" role="status" x-data="{ wait: {{ $retryIn }} }" x-init="if (wait > 0) { const t = setInterval(() => { if (--wait <= 0) clearInterval(t) }, 1000) }">
                    <b>We're checking your bet</b>
                    <p class="nt-num">{{ \App\Predict\Format::kes((float) $stake) }} on {{ $label($side) }}. We'll never take it twice: trying again sends the same bet.</p>
                    <x-ui.button block wire:click="retry" wire:loading.attr="disabled" wire:target="retry" x-bind:disabled="wait > 0">
                        <span wire:loading.remove wire:target="retry"><span x-show="wait <= 0">Try again</span><span x-show="wait > 0" x-cloak>Try again in <span x-text="wait"></span>s</span></span>
                        <span wire:loading wire:target="retry">Checking…</span>
                    </x-ui.button>
                </div>
            @elseif ($side !== '')
                {{-- Stake --}}
                <div class="nt-pm-stake">
                    <label for="pm-stake-{{ $marketId }}">Stake</label>
                    <div class="nt-pm-stake-input">
                        <span aria-hidden="true">KES</span>
                        <input id="pm-stake-{{ $marketId }}" type="text" inputmode="decimal" autocomplete="off" placeholder="0"
                               wire:model.live.debounce.300ms="stake" aria-describedby="pm-stake-help-{{ $marketId }}">
                    </div>
                    @if ($quickStakes !== [])
                        <div class="nt-pm-quick" role="group" aria-label="Quick stakes">
                            @foreach ($quickStakes as $amount)
                                <button type="button" class="nt-chip" wire:click="useStake({{ $amount }})" aria-pressed="{{ (string) $amount === $stake ? 'true' : 'false' }}">KES {{ $amount }}</button>
                            @endforeach
                        </div>
                    @endif
                    <small id="pm-stake-help-{{ $marketId }}" class="nt-num">
                        @if ($market->minStake !== null)Min {{ \App\Predict\Format::kes($market->minStake) }}@endif
                        @if ($market->maxStake !== null) · Max {{ \App\Predict\Format::kes($market->maxStake) }}@endif
                    </small>
                </div>

                {{-- Figures --}}
                @if ($quote)
                    <div class="nt-pm-quote" role="status" wire:key="quote-{{ $quote['id'] }}"
                         x-data="{ left: {{ $quoteSeconds }} }" x-init="const t = setInterval(() => { if (--left <= 0) { left = 0; clearInterval(t) } }, 1000)">
                        <p class="nt-num"><b>Pays {{ \App\Predict\Format::multiplier($quote['multiplier']) }}</b>: {{ \App\Predict\Format::kes($quote['payout']) }} if {{ $label($quote['side']) }} wins</p>
                        <small x-show="left > 0">Price held for <span class="nt-num" x-text="left">{{ $quoteSeconds }}</span>s</small>
                        <small x-show="left <= 0" x-cloak>This price has expired.</small>
                    </div>
                @elseif ($estimate)
                    <p class="nt-pm-estimate nt-num" role="status">
                        {{ $usesQuote ? 'Indicative' : 'Estimated' }}: {{ \App\Predict\Format::multiplier($estimate['multiplier']) }}, {{ \App\Predict\Format::kes($estimate['payout']) }} if {{ $label($side) }} wins
                    </p>
                @endif

                {{-- The price moved --}}
                @if ($changed)
                    <div class="nt-pm-changed" role="alert">
                        <p>Odds changed{{ $changed['multiplier'] ? ': now '.\App\Predict\Format::multiplier($changed['multiplier']) : '' }}.</p>
                        <x-ui.button block wire:click="acceptChange" wire:loading.attr="disabled">{{ $changed['quote'] ? 'Accept and place' : 'Get the new price' }}</x-ui.button>
                    </div>
                @endif

                {{-- Main action --}}
                @unless ($changed)
                    @if ($usesQuote)
                        @if ($quote)
                            <div x-data="{ left: {{ $quoteSeconds }} }" x-init="const t = setInterval(() => { if (--left <= 0) { left = 0; clearInterval(t) } }, 1000)" wire:key="act-{{ $quote['id'] }}">
                                <x-ui.button block x-show="left > 0" wire:click="place" wire:loading.attr="disabled" wire:target="place" data-predict-place>
                                    <span wire:loading.remove wire:target="place">Place bet</span><span wire:loading wire:target="place">Placing…</span>
                                </x-ui.button>
                                <x-ui.button block x-show="left <= 0" x-cloak wire:click="getQuote" wire:loading.attr="disabled" wire:target="getQuote">Get a new price</x-ui.button>
                            </div>
                        @else
                            <x-ui.button block wire:click="getQuote" wire:loading.attr="disabled" wire:target="getQuote" data-predict-get-price>
                                <span wire:loading.remove wire:target="getQuote">Get price</span><span wire:loading wire:target="getQuote">Getting price…</span>
                            </x-ui.button>
                        @endif
                    @else
                        <x-ui.button block wire:click="place" wire:loading.attr="disabled" wire:target="place" data-predict-place>
                            <span wire:loading.remove wire:target="place">Place bet</span><span wire:loading wire:target="place">Placing…</span>
                        </x-ui.button>
                    @endif
                @endunless
            @else
                <p class="nt-pm-note">Pick Yes or No to place a bet.</p>
            @endif

            {{-- What went wrong, and the one thing to do about it --}}
            @if ($notice && $state !== 'checking')
                <div class="nt-pm-notice" role="alert">
                    <p>{{ $notice['message'] }}</p>
                    @switch($notice['action'])
                        @case(\App\Predict\Refusal::DEPOSIT)
                            <button type="button" x-on:click="$dispatch('predict-panel-handoff'); $dispatch('open-deposit')">Deposit</button>
                            @break
                        @case(\App\Predict\Refusal::USE_STAKE)
                            @if ($notice['amount'])
                                <button type="button" wire:click="useStake({{ $notice['amount'] }})">Bet {{ \App\Predict\Format::kes($notice['amount']) }}</button>
                            @endif
                            @break
                        @case(\App\Predict\Refusal::OTHER_SIDE)
                            <button type="button" wire:click="pick('{{ $side === 'yes' ? 'no' : 'yes' }}')">Bet {{ $label($side === 'yes' ? 'no' : 'yes') }} instead</button>
                            @break
                        @case(\App\Predict\Refusal::RETRY)
                            @if ($notice['code'] === 'pending_elsewhere' && $notice['amount'])
                                <a href="{{ route('predictions.market', (int) $notice['amount']) }}">Open that market</a>
                            @endif
                            @break
                    @endswitch
                </div>
            @endif
        @endguest

        <p class="nt-pm-explain">
            @if ($usesQuote)
                The multiplier is indicative. When you bet, you get a fixed price for 10 seconds, and that is what your bet pays.
            @else
                This is a pool: the multiplier is an estimate and moves as people bet. Your payout is fixed when the market closes.
            @endif
        </p>
    @endif
</section>
