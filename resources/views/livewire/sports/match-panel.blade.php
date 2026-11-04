<div x-data="matchPanel" x-on:open-match.window="open($event.detail)" x-on:keydown.escape.window="isOpen && close()" data-match-panel>
    <div class="nt-scrim" x-show="isOpen" x-cloak x-transition.opacity x-on:click="close()" aria-hidden="true"></div>
    <section class="nt-sheet nt-match-panel" role="dialog" aria-modal="true" aria-labelledby="match-panel-title"
             x-show="isOpen" x-cloak x-trap.inert.noscroll="isOpen"
             x-transition:enter="nt-sheet-anim" x-transition:enter-start="nt-sheet-from" x-transition:enter-end=""
             x-transition:leave="nt-sheet-anim" x-transition:leave-start="" x-transition:leave-end="nt-sheet-from"
             data-test="match-panel">
        <header class="nt-match-panel-head">
            <h2 id="match-panel-title" x-text="title"></h2>
            <a class="nt-btn nt-btn-quiet nt-btn-sm" x-bind:href="url">Full page</a>
            <button type="button" class="nt-icon-btn" x-on:click="close()" aria-label="Close"><x-site.icon name="x" /></button>
        </header>
        <div class="nt-match-panel-body">
            <div x-show="loaded !== requested" class="nt-skeleton-stack" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
            <div x-show="loaded === requested">
                @if ($fixture = $this->fixture)
                    <x-sports.match-details :$fixture :markets-by-tab="$this->marketsByTab" :$tab in-panel />
                @else
                    <div class="nt-empty"><b>Match not available</b>This match is no longer open for betting.</div>
                @endif
            </div>
        </div>
        <div class="nt-match-panel-foot">
            {{-- The panel traps focus and hides the rest of the page, so it closes before the slip opens. --}}
            <button type="button" class="nt-btn nt-btn-gold nt-btn-block" x-on:click="close(); $store.betslip.isOpen = true">
                Bet slip <span class="nt-num" x-text="'(' + $store.betslip.count() + ')'"></span>
            </button>
        </div>
    </section>
</div>
