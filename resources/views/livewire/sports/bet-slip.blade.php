@php
    use App\Sportsbook\Betting\BetNotPlaced;
    use App\Sportsbook\Enums\BetStatus;
    use App\Sportsbook\Enums\OddsChangePolicy;
    use App\Sportsbook\Support\Money;

    $quote = $this->quote;
    $placed = $this->placedBet;
    $freeBet = $this->selectedFreeBet;
    $balance = $this->knownBalance;
    $overBalance = ! $freeBet && $quote->stake !== null && $balance !== null && $quote->stake > $balance;
    $belowFreeBetOdds = $freeBet && $quote->bettableCount() > 0 && round($quote->totalOdds * 100) < round((float) $freeBet->min_odds * 100);
    // The known balance is only a hint (a deposit may not show here yet): it never disables placing.
    $canPlace = $quote->bettableCount() > 0 && ! $quote->hasUnavailable() && $quote->stake !== null && $quote->stakeErrors === [] && ! $belowFreeBetOdds;
@endphp
{{--
    The bet slip host (plan §2): beside the page on wide sports pages (body.nt-has-rail, 1240px and up), a right
    drawer or bottom sheet everywhere else, opened from the bottom bar, the floating pill or the match panel.
--}}
<div class="nt-slip-host"
     x-data="{
        rail: document.body.classList.contains('nt-has-rail'),
        wide: window.innerWidth >= 1240,
        dock: 88,
        asSheet() { return ! (this.rail && this.wide) },
        {{-- Docked, the slip starts 24px under the top bar, or under the notice strip while it is in view. --}}
        place() {
            const top = parseFloat(getComputedStyle(document.body).getPropertyValue('--nt-top')) || 64;
            const notice = document.querySelector('.nt-notice');
            this.dock = Math.round(Math.max(top, notice ? notice.getBoundingClientRect().bottom : 0)) + 24;
        },
     }"
     x-on:scroll.window.passive="rail && wide && place()"
     x-on:resize.window.debounce.100ms="wide = window.innerWidth >= 1240; place()"
     x-on:livewire:navigated.document="rail = document.body.classList.contains('nt-has-rail'); place()"
     x-bind:class="{ 'is-open': $store.betslip.isOpen }"
     x-init="place(); if ($store.betslip.count() > 0) { $wire.syncPicks($store.betslip.picks) }"
     x-on:betslip-changed.window="$wire.syncPicks($event.detail.picks)"
     x-on:betslip-accept-prices.window="$store.betslip.acceptPrices($event.detail.prices)"
     x-on:betslip-remove.window="$event.detail.selectionIds.forEach((id) => $store.betslip.remove(id))"
     x-on:betslip-replace.window="$store.betslip.replace($event.detail.picks); $store.betslip.isOpen = true"
     x-on:betslip-clear.window="$store.betslip.clear()"
     x-on:keydown.escape.window="if ($store.betslip.isOpen && asSheet()) { $store.betslip.isOpen = false; $event.stopImmediatePropagation() }"
     data-bet-slip>
    <div class="nt-scrim nt-slip-scrim" x-show="$store.betslip.isOpen" x-cloak x-transition.opacity x-on:click="$store.betslip.isOpen = false"></div>

    <button type="button" class="nt-slip-pill nt-slip-float" x-cloak
            x-show="$store.betslip.count() > 0 && ! $store.betslip.isOpen" x-on:click="$store.betslip.isOpen = true"
            x-bind:aria-label="'Bet slip, ' + $store.betslip.count() + ($store.betslip.count() === 1 ? ' selection' : ' selections')">
        <span>Bet slip</span>
        <span class="nt-count nt-num" aria-hidden="true" x-text="$store.betslip.count()"></span>
    </button>

    <aside class="nt-slip-dock" id="bet-slip" aria-label="Bet slip and open bets"
           x-bind:style="asSheet() ? '' : `top: ${dock}px; max-height: calc(100dvh - ${dock + 24}px)`"
           x-trap.noscroll="$store.betslip.isOpen && asSheet()"
           x-bind:role="asSheet() ? 'dialog' : null" x-bind:aria-modal="asSheet() ? 'true' : null">
        <section class="nt-panel nt-slip" aria-labelledby="slip-title">
            <header class="nt-slip-head">
                <h2 id="slip-title">Bet slip</h2>
                <span class="nt-slip-count nt-num" x-bind:class="{ 'is-on': $store.betslip.count() > 0 }" x-text="$store.betslip.count()">{{ count($quote->lines) }}</span>
                <span class="nt-slip-tools">
                    <button type="button" class="nt-slip-clear" x-show="$store.betslip.count() > 0" x-on:click="$store.betslip.clear()">Clear all</button>
                    <button type="button" class="nt-icon-btn nt-slip-close" x-show="asSheet()" x-on:click="$store.betslip.isOpen = false" aria-label="Close bet slip"><x-site.icon name="x" /></button>
                </span>
            </header>

            @if ($placed)
                <div class="nt-slip-done" role="status" data-test="bet-placed"
                     @if ($placed->status === BetStatus::Pending) wire:poll.3s="refreshPlaced" @endif>
                    @if ($placed->status === BetStatus::Pending)
                        <b>Checking your bet…</b>
                        <p>Your wallet hasn't answered yet. We're checking, and your stake can only be taken once.</p>
                    @elseif ($placed->status === BetStatus::Rejected)
                        <b>Your bet wasn't placed</b>
                        <p>Nothing was taken from your wallet. You can build the slip again.</p>
                    @else
                        <b class="nt-ok">Bet placed</b>
                        <dl>
                            <div><dt>Bet ID</dt><dd class="nt-num" data-test="bet-reference">{{ $placed->reference }}</dd></div>
                            <div><dt>{{ $placed->type->label() }}</dt><dd class="nt-num">Odds {{ $placed->total_odds }}</dd></div>
                            <div><dt>{{ $placed->isFreeBet() ? 'Free bet stake' : 'Stake' }}</dt><dd class="nt-num">{{ Money::format($placed->stake) }}</dd></div>
                            @if ($placed->bonus_percent > 0)
                                <div><dt>Multibet bonus</dt><dd class="nt-num">+{{ $placed->bonus_percent }}%</dd></div>
                            @endif
                            <div><dt>Possible payout (before tax)</dt><dd class="nt-num nt-gold">{{ Money::format($placed->potential_payout) }}</dd></div>
                        </dl>
                    @endif
                    @if ($placed->status !== BetStatus::Pending)
                        <div class="nt-slip-actions">
                            @if (Route::has('activity'))
                                <a class="nt-btn nt-btn-ghost" href="{{ route('activity', ['game' => 'sports']) }}" wire:navigate>My bets</a>
                            @endif
                            <button type="button" class="nt-btn nt-btn-gold" wire:click="newSlip">New slip</button>
                        </div>
                    @endif
                </div>
            @else
                <div class="nt-slip-empty" x-show="$store.betslip.count() === 0">
                    <span class="nt-slip-ic" aria-hidden="true"><x-site.icon name="slip" /></span>
                    <b>Your bet slip is empty</b>
                    <p>Tap any price to add it. Three or more selections can earn a multibet bonus.</p>
                </div>

                <ul class="nt-picks" x-show="$store.betslip.count() > 0" x-cloak>
                    @foreach ($quote->lines as $line)
                        @php $selection = $line['selection']; $fixture = $selection->market->fixture; @endphp
                        <li class="nt-pick" wire:key="slip-{{ $selection->id }}" x-show="$store.betslip.has({{ $selection->id }})" @if ($line['status'] === 'unavailable') data-closed @endif>
                            <span class="nt-pick-what">
                                <b>{{ $selection->name }}</b>
                                <span>{{ $selection->market->name() }} · <a href="{{ route('sports.match', $fixture) }}" x-on:click="$store.betslip.isOpen = false; $openMatch($event, {{ $fixture->id }}, @js($fixture->name()))">{{ $fixture->name() }}</a></span>
                                @if ($line['message'])<span class="nt-pick-note">{{ $line['message'] }}</span>@endif
                            </span>
                            <span class="nt-pick-price nt-num">
                                @if ($line['status'] === 'unavailable')
                                    –
                                @else
                                    <b @class(['nt-gold' => $line['status'] === 'price_changed'])>{{ $selection->price() }}</b>
                                    @if ($line['status'] === 'price_changed')<s>{{ $line['seen_price'] }}</s>@endif
                                @endif
                            </span>
                            <button type="button" class="nt-pick-x" x-on:click="$store.betslip.remove({{ $selection->id }})" aria-label="Remove {{ $selection->name }}"><x-site.icon name="x" /></button>
                        </li>
                    @endforeach
                    {{-- Picks the server hasn't checked yet, shown at once from what the browser knows. --}}
                    <template x-for="pick in $store.betslip.picks.filter((p) => ! @js(collect($quote->lines)->map(fn ($l) => $l['selection']->id)->values()).includes(p.selection_id))" x-bind:key="pick.selection_id">
                        <li class="nt-pick" data-test="slip-pending">
                            <span class="nt-pick-what"><b x-text="pick.name || 'Selection'"></b><span x-text="[pick.market, pick.match].filter(Boolean).join(' · ')"></span></span>
                            <span class="nt-pick-price nt-num"><b x-text="pick.price"></b></span>
                            <button type="button" class="nt-pick-x" x-on:click="$store.betslip.remove(pick.selection_id)" x-bind:aria-label="'Remove ' + (pick.name || 'selection')"><x-site.icon name="x" /></button>
                        </li>
                    </template>
                </ul>

                @if (count($quote->lines) > 0)
                    <div class="nt-slip-body" x-show="$store.betslip.count() > 0" wire:loading.class="opacity-60" wire:target="syncPicks">
                        @if ($quote->hasUnavailable())
                            <button type="button" class="nt-btn nt-btn-ghost nt-btn-block nt-btn-sm" wire:click="removeUnavailable">Remove closed selections</button>
                        @endif
                        @if ($quote->hasPriceChanges())
                            <div class="nt-slip-warn">
                                <p>Some prices changed since you added them.</p>
                                <button type="button" class="nt-btn nt-btn-gold nt-btn-sm nt-btn-block" wire:click="acceptPriceChanges">Accept the new prices</button>
                            </div>
                        @endif

                        <p class="nt-line"><span>{{ $quote->bettableCount() > 1 ? 'Multibet, '.$quote->bettableCount().' selections' : 'Single' }}</span><b class="nt-num">{{ number_format($quote->totalOdds, 2) }}</b></p>

                        @if ($this->freeBets->isNotEmpty())
                            <label class="nt-policy">Pay with
                                <select wire:model.live="freeBetId" data-test="free-bet">
                                    <option value="">Cash</option>
                                    @foreach ($this->freeBets as $option)
                                        <option value="{{ $option->id }}">{{ Money::format($option->amount) }} free bet (odds {{ $option->min_odds }}+)</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        @if ($freeBet)
                            <p class="nt-slip-note" data-test="free-bet-terms">Your {{ Money::format($freeBet->amount) }} free bet is the stake. If it wins, you get the winnings above the stake.</p>
                            @if ($belowFreeBetOdds)
                                <p class="nt-slip-error" role="alert">This free bet needs total odds of at least {{ $freeBet->min_odds }}.</p>
                            @endif
                        @else
                            <label class="nt-stake"><span>KES</span>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" wire:model.live.debounce.300ms="stake" aria-label="Stake in KES"
                                       placeholder="{{ config('sportsbook.limits.min_stake') }}" autocomplete="off">
                            </label>
                            <div class="nt-quick">
                                @foreach ([50, 100, 500, 1000] as $amount)
                                    <button type="button" x-on:click="$wire.$set('stake', '{{ $amount }}')">{{ number_format($amount) }}</button>
                                @endforeach
                            </div>
                            @foreach ($quote->stakeErrors as $error)
                                <p class="nt-slip-error" role="alert">{{ $error }}</p>
                            @endforeach
                            @if ($overBalance)
                                <p class="nt-slip-error" role="alert">That's more than your wallet holds ({{ 'KES '.number_format($balance, 2) }}). <button type="button" x-on:click="$dispatch('open-deposit')">Deposit</button> or lower the stake.</p>
                            @endif
                        @endif

                        @if ($quote->bonusPercent > 0)
                            <p class="nt-line" data-test="multibet-bonus"><span>Multibet bonus</span><b class="nt-num nt-ok">+{{ $quote->bonusPercent }}%@if ($quote->stake !== null) · KES {{ number_format($quote->bonus, 2) }}@endif</b></p>
                        @elseif ($quote->bettableCount() >= 1 && ! $freeBet && ($next = $quote->nextBonusTier()))
                            <p class="nt-line" data-test="next-bonus"><span>Add {{ $next['more'] }} more at odds {{ config('sportsbook.promotions.multibet_bonus.min_price') }}+ for a {{ $next['percent'] }}% bonus</span><b class="nt-num">–</b></p>
                        @endif

                        <p class="nt-line nt-payout"><span>Possible payout</span><b class="nt-num">KES {{ number_format($quote->possibleWin, 2) }}</b></p>
                        @if ($quote->isPayoutCapped)
                            <p class="nt-slip-note">Payouts are capped at KES {{ number_format((float) config('sportsbook.limits.max_payout')) }}.</p>
                        @endif

                        @auth
                            @if ($this->blocked)
                                <p class="nt-slip-note" data-test="cannot-bet">{{ $this->blocked }}</p>
                            @else
                                <label class="nt-policy">If prices change
                                    <select wire:model.live="oddsPolicy" data-test="odds-policy">
                                        @foreach (OddsChangePolicy::cases() as $policy)
                                            <option value="{{ $policy->value }}">{{ ['any' => 'Accept any', 'higher' => 'Accept higher', 'none' => 'Ask me'][$policy->value] }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                @error('bet')
                                    <p class="nt-slip-error" role="alert" data-test="bet-error">{{ $message }}
                                        @if ($refusal === BetNotPlaced::INSUFFICIENT_FUNDS)
                                            <button type="button" x-on:click="$dispatch('open-deposit')">Deposit</button>
                                        @endif
                                    </p>
                                @enderror
                            @endif
                        @endauth

                        <div class="nt-slip-actions">
                            @if ($bookingCode)
                                <span></span>
                            @else
                                <button type="button" class="nt-btn nt-btn-ghost" wire:click="createBookingCode">Share</button>
                            @endif
                            @auth
                                @unless ($this->blocked)
                                    <button type="button" class="nt-btn nt-btn-gold" wire:click="placeBet" wire:loading.attr="disabled" wire:target="placeBet" @disabled(! $canPlace) data-test="place-bet">
                                        <span wire:loading.remove wire:target="placeBet">{{ $freeBet ? 'Place free bet' : ($quote->stake !== null ? 'Place bet KES '.number_format($quote->stake) : 'Place bet') }}</span>
                                        <span wire:loading wire:target="placeBet">Checking your bet…</span>
                                    </button>
                                @endunless
                            @else
                                <a class="nt-btn nt-btn-gold" href="{{ route('login') }}" data-signin>Log in to bet</a>
                            @endauth
                        </div>

                        @if ($bookingCode)
                            <div class="nt-code-share" x-data="{ copied: false, link: @js(route('sports', ['betslip' => $bookingCode])), text: @js('My Kadi bet slip: '.$bookingCode) }">
                                <span>Booking code</span>
                                <b class="nt-num">{{ $bookingCode }}</b>
                                <div class="nt-share-row">
                                    <a target="_blank" rel="noopener" x-bind:href="'https://wa.me/?text=' + encodeURIComponent(text + ' ' + link)">WhatsApp</a>
                                    <a target="_blank" rel="noopener" x-bind:href="'https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent(text)">Telegram</a>
                                    <a x-bind:href="'sms:?&body=' + encodeURIComponent(text + ' ' + link)">SMS</a>
                                    <button type="button" x-on:click="navigator.share ? navigator.share({ text, url: link }).catch(() => {}) : (navigator.clipboard?.writeText(link), copied = true)"><span x-text="copied ? 'Copied' : 'More'">More</span></button>
                                </div>
                            </div>
                        @endif
                        @error('booking')<p class="nt-slip-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                @else
                    <div class="nt-slip-body" x-show="$store.betslip.count() > 0" x-cloak data-test="slip-checking">
                        <p class="nt-line"><span>Checking prices…</span><b class="nt-num" x-text="$store.betslip.seenOdds()"></b></p>
                    </div>
                @endif
            @endif

            <form class="nt-code" wire:submit="loadBookingCode">
                <label for="booking-code-input" class="sr-only">Booking code</label>
                <input id="booking-code-input" type="text" wire:model="codeToLoad" maxlength="12" autocapitalize="characters" autocomplete="off" placeholder="Booking code">
                <button type="submit" class="nt-btn nt-btn-ghost">Load</button>
            </form>
            @error('loadCode')<p class="nt-slip-error nt-code-error" role="alert">{{ $message }}</p>@enderror
            @if ($unavailableFromCode !== [])
                <div class="nt-slip-note nt-code-error">
                    <p>No longer available, so not added:</p>
                    <ul>@foreach ($unavailableFromCode as $description)<li>{{ $description }}</li>@endforeach</ul>
                </div>
            @endif

            <p class="nt-slip-foot nt-num">
                @if ($balance !== null)
                    Paid from your Kadi wallet: KES {{ number_format($balance, 2) }}
                @else
                    {{ config('sportsbook.minimum_age') }}+ only. Bet with money you can afford to lose.
                @endif
            </p>
        </section>

        @auth
            @if ($this->openBets->isNotEmpty())
                <section class="nt-panel nt-open-bets" aria-labelledby="open-bets-title">
                    <h2 id="open-bets-title">Open bets</h2>
                    @foreach ($this->openBets as $bet)
                        <div class="nt-open-bet">
                            <span>{{ $bet->legs->pluck('selection_name')->take(3)->implode(', ') }}
                                <small>{{ $bet->status === BetStatus::Pending ? 'Checking…' : ($bet->legs->count() > 1 ? 'Multibet, '.$bet->legs->count().' legs' : 'Single') }}</small></span>
                            <span class="nt-num">{{ Money::format($bet->stake) }}<small>pays {{ Money::format($bet->potential_payout) }}</small></span>
                        </div>
                    @endforeach
                    @if (Route::has('activity'))
                        <a class="nt-see-all-row" href="{{ route('activity', ['game' => 'sports']) }}" wire:navigate>All my bets</a>
                    @endif
                </section>
            @endif
        @endauth
    </aside>
</div>
