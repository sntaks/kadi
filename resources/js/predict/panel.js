/**
 * The prediction sheet (resources/views/livewire/predict/market-panel.blade.php): a market, or the list of an
 * event's markets, sliding over the current page instead of loading a new one.
 *
 * Opening a market adds a history entry for the market's URL, so the address bar shows it and Back closes the sheet
 * (or returns to the event list it was opened from). As in the sports match panel (resources/js/sports/match-panel.js),
 * the entry keeps Livewire's own history state and the path of the page under the sheet, and this popstate handler
 * runs before Livewire's only for entries that belong to this page.
 *
 * Speed: the header (picture and question) comes from the link that was clicked and shows at once; the details come
 * from the server (from the copy the list already primed). The price chart is on the full page only. An event list
 * is copied from the card's own <template> and needs no request.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('predictPanel', () => ({
        isOpen: false,
        view: 'market',
        requested: null,
        loaded: null,
        title: '',
        image: null,
        url: '',
        eventTitle: null,
        pushed: false,
        skipPop: false,
        pageTitle: null,
        returnFocus: null,
        basePath: window.location.pathname,

        init() {
            const restored = window.history.state?.predictPanel;
            this.basePath = restored?.from ?? window.location.pathname;

            this.onPopState = (event) => {
                const entry = event.state?.predictPanel;

                // Our own Back after handing over to another sheet: the sheet is already closed.
                if (this.skipPop) {
                    event.stopImmediatePropagation();
                    this.skipPop = false;

                    return;
                }

                if (this.pushed) {
                    event.stopImmediatePropagation();
                    this.pushed = false;

                    // Back from a market opened out of an event list: show the list again.
                    if (this.view === 'market' && this.eventTitle) {
                        this.view = 'event';
                        document.title = this.pageTitle ?? document.title;
                    } else {
                        this.hide();
                    }
                } else if (entry && entry.from === this.basePath) {
                    event.stopImmediatePropagation();
                    this.open(entry, { fromHistory: true });
                }
            };

            window.addEventListener('popstate', this.onPopState, true);

            // A reload while the sheet was open loads the full market page at that address: no sheet over it.
            if (restored && !document.querySelector('[data-predict-page]')) {
                this.$nextTick(() => this.open(restored, { fromHistory: true }));
            }
        },

        destroy() {
            window.removeEventListener('popstate', this.onPopState, true);
        },

        remember() {
            if (!this.isOpen) {
                this.pageTitle = document.title;
                this.returnFocus = document.activeElement;
            }
        },

        open({ id, title, url, image = null, pageTitle = null }, { fromHistory = false } = {}) {
            this.remember();

            if (pageTitle) this.pageTitle = pageTitle;

            this.view = 'market';
            this.requested = id;
            this.title = title;
            this.image = image;
            this.url = url;
            document.title = title;

            const state = {
                ...(window.history.state ?? {}),
                predictPanel: { id, title, url, image, from: this.basePath, pageTitle: this.pageTitle },
            };

            if (fromHistory || this.pushed) {
                window.history.replaceState(state, '', url);
            } else {
                window.history.pushState(state, '', url);
            }

            this.pushed = true;
            this.isOpen = true;

            if (this.loaded !== id) {
                this.$wire.show(id).then(() => {
                    if (this.requested === id) {
                        this.loaded = id;
                    }
                });
            }
        },

        openEvent({ title, template }) {
            this.remember();
            this.view = 'event';
            this.eventTitle = title;
            this.$refs.eventRows.replaceChildren(template.content.cloneNode(true));
            this.isOpen = true;
        },

        backToEvent() {
            if (this.pushed) {
                window.history.back();
            } else {
                this.view = 'event';
            }
        },

        close() {
            if (this.pushed) {
                // Back returns to the event list when the market came from one: close it outright instead.
                this.eventTitle = null;
                window.history.back();
            } else {
                this.hide();
            }
        },

        /**
         * Close at once for another sheet (the sign-in sheet): no focus handed back to the page (the other sheet takes
         * it), and our history entry dropped quietly. The address bar still shows the market while the click is
         * handled, so the sign-in sheet's `return_to` is the market.
         */
        handOff() {
            this.returnFocus = null;
            this.eventTitle = null;

            if (this.pushed) {
                this.pushed = false;
                this.skipPop = true;
                window.history.back();
            }

            this.hide();
        },

        hide() {
            this.isOpen = false;
            this.eventTitle = null;

            if (this.pageTitle !== null) {
                document.title = this.pageTitle;
            }

            this.returnFocus?.focus?.();
        },
    }));

    /**
     * Click handler for market links: opens the market in the sheet, but leaves new-tab and modified clicks to the
     * browser, and loads the page itself where no sheet exists.
     */
    window.Alpine.magic('openMarket', (el) => (event, id, title, image = null) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        const url = el.href.split('?')[0];

        if (document.querySelector('[data-predict-panel]')) {
            window.dispatchEvent(new CustomEvent('open-predict-market', { detail: { id, title, url, image } }));
        } else {
            window.Livewire.navigate(el.href);
        }
    });

    /** "+N more" on an event card: the event's markets in the sheet, copied from the card's <template>. */
    window.Alpine.magic('openEvent', (el) => (title) => {
        const template = el.closest('[data-predict-event]')?.querySelector('template[data-event-rows]');

        if (template && document.querySelector('[data-predict-panel]')) {
            window.dispatchEvent(new CustomEvent('open-predict-event', { detail: { title, template } }));
        }
    });
});
