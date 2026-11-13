{{--
    The prediction sheet (App\Livewire\Predict\MarketPanel, resources/js/predict/panel.js). Two views:
      market  one market, rendered by the server; the header (picture, question) shows at once from the link that
              opened it, the rest when the details arrive (no chart here: the full page has it);
      event   every market of an event, copied from the card's own <template> (no request at all).
--}}
<div x-data="predictPanel" x-on:open-predict-market.window="open($event.detail)" x-on:open-predict-event.window="openEvent($event.detail)" x-on:predict-panel-handoff.window="isOpen && handOff()"
     x-on:keydown.escape.window="isOpen && close()" data-predict-panel data-predict-live>
    <div class="nt-scrim" x-show="isOpen" x-cloak x-transition.opacity x-on:click="close()" aria-hidden="true"></div>
    <section class="nt-sheet nt-match-panel nt-pm-sheet" role="dialog" aria-modal="true" aria-labelledby="predict-panel-title"
             x-show="isOpen" x-cloak x-trap.inert.noscroll="isOpen"
             x-transition:enter="nt-sheet-anim" x-transition:enter-start="nt-sheet-from" x-transition:enter-end=""
             x-transition:leave="nt-sheet-anim" x-transition:leave-start="" x-transition:leave-end="nt-sheet-from"
             data-test="predict-panel">
        <header class="nt-match-panel-head">
            <button type="button" class="nt-icon-btn" x-show="view === 'market' && eventTitle" x-cloak x-on:click="backToEvent()" aria-label="Back to the event"><x-site.icon name="chev" /></button>
            <h2 id="predict-panel-title" x-text="view === 'event' ? eventTitle : 'Market'"></h2>
            <a class="nt-btn nt-btn-quiet nt-btn-sm" x-show="view === 'market'" x-bind:href="url">Full page</a>
            <button type="button" class="nt-icon-btn" x-on:click="close()" aria-label="Close"><x-site.icon name="x" /></button>
        </header>

        <div class="nt-match-panel-body">
            {{-- Event list: filled from the card's template. --}}
            <ul class="nt-pm-rows nt-pm-sheet-rows" x-show="view === 'event'" x-ref="eventRows"></ul>

            <div x-show="view === 'market'" class="nt-pm-sheet-market">
                <div class="nt-pm-sheet-head">
                    <span class="nt-pm-img" aria-hidden="true">
                        <x-site.icon name="predict" />
                        <template x-if="image"><img x-bind:src="image" alt="" referrerpolicy="no-referrer" x-on:error="$el.remove()"></template>
                    </span>
                    <p class="nt-pm-sheet-q" x-text="title"></p>
                </div>

                <div x-show="loaded !== requested" class="nt-skeleton-stack" aria-hidden="true"><span></span><span></span><span></span></div>

                <div x-show="loaded === requested" class="nt-pm-sheet-body">
                    @if ($market)
                        @php($reason = $market->unavailableReason($now))
                        @php($left = \App\Predict\Format::timeLeft($market->bettingClosesAt, $now))
                        <p class="nt-pm-kicker" data-predict-market="{{ $market->id }}">
                            @if ($market->eventTitle)<span>{{ $market->eventTitle }}</span>@endif
                            <span data-predict-status data-open-text="{{ $left ? 'Ends in '.$left : 'Open' }}" @class(['nt-pm-reason' => $reason !== null])>{{ $reason ?? ($left ? 'Ends in '.$left : 'Open') }}</span>
                        </p>
                        <div class="nt-pm-sheet-chances" data-predict-market="{{ $market->id }}">
                            <x-predict.chances :$market />
                            <x-predict.attribution :odds="$market->odds" />
                        </div>
                        <livewire:predict.slip :market-id="$market->id" :initial-side="$side" :key="'sheet-slip-'.$market->id.'-'.$opened" />
                        <x-predict.stats :$market />
                        <x-predict.rules :$market :$criteria />
                    @elseif ($marketId !== null)
                        <div class="nt-empty"><b>Market not available</b>This market is no longer shown.</div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
