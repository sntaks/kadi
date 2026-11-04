{{--
    Top bar search over the game catalogue. The list (a few kilobytes) is fetched once, on first focus,
    from GET /games/search, then filtered in the browser so typing never waits on the server. Shown only
    once games open inside the site, because a result has to lead somewhere.
    "/" focuses it; Down moves into the results; Esc closes them.
    Teams, competitions and matches join the list while the sports pages are on (Phase 9).
--}}
@php($sportsSearch = \App\Support\SportsPages::on())
@if (config('platform.token_launch') || $sportsSearch)
    <div class="nt-search" role="search"
         x-data="{
            q: '',
            open: false,
            entries: [],
            loading: false,
            ready: false,
            failed: false,
            load() {
                if (this.ready || this.loading) return;
                this.loading = true;
                this.failed = false;
                fetch(@js(route('games.search')), { headers: { Accept: 'application/json' } })
                    .then((r) => (r.ok ? r.json() : Promise.reject()))
                    .then((json) => { this.entries = json.data ?? []; this.ready = true; })
                    .catch(() => { this.failed = true; })
                    .finally(() => { this.loading = false; });
            },
            get results() {
                const q = this.q.trim().toLowerCase();
                if (q.length < 2) return [];
                return this.entries.filter((e) => e.name.toLowerCase().includes(q) || e.kind.toLowerCase().includes(q)).slice(0, 8);
            },
            first() { this.$refs.results?.querySelector('a')?.focus(); },
         }"
         x-on:keydown.window.slash="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) && ! document.activeElement?.isContentEditable) { $event.preventDefault(); $refs.input.focus() }"
         x-on:keydown.escape="open = false; $refs.input.focus()"
         x-on:click.outside="open = false"
         x-on:focusout="if (! $el.contains($event.relatedTarget)) open = false">
        <label class="nt-search-box">
            <x-site.icon name="search" />
            <span class="sr-only">{{ $sportsSearch ? 'Search teams, leagues or games' : 'Search games' }}</span>
            <input type="search" x-ref="input" x-model="q" x-on:focus="open = true; load()" x-on:input="open = true"
                   x-on:keydown.down.prevent="first()"
                   placeholder="{{ $sportsSearch ? 'Search teams, games' : 'Search games' }}" autocomplete="off" aria-controls="site-search-results"
                   x-bind:aria-expanded="open && q.trim().length >= 2">
            <kbd aria-hidden="true">/</kbd>
        </label>
        <div id="site-search-results" class="nt-search-results" x-ref="results" x-show="open && q.trim().length >= 2" x-cloak
             x-on:keydown.down.prevent="$focus.wrap().next()" x-on:keydown.up.prevent="$focus.wrap().previous()">
            <template x-for="entry in results" x-bind:key="entry.url">
                <a x-bind:href="entry.url">
                    <template x-if="entry.thumb"><img x-bind:src="entry.thumb" alt="" width="32" height="48" loading="lazy"></template>
                    <template x-if="! entry.thumb"><span class="nt-thumb" aria-hidden="true"></span></template>
                    <span><span x-text="entry.name"></span><small x-text="entry.kind"></small></span>
                </a>
            </template>
            <p x-show="failed">Search isn't available right now. Try again in a moment.</p>
            <p x-show="loading">Loading…</p>
            <p x-show="ready && results.length === 0">Nothing matches "<span x-text="q.trim()"></span>".</p>
        </div>
        <p class="sr-only" aria-live="polite" x-text="ready && open && q.trim().length >= 2 ? results.length + (results.length === 1 ? ' result' : ' results') : ''"></p>
    </div>
@endif
