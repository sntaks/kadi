/**
 * The match panel: a match's markets sliding over the current page instead of loading the match page.
 *
 * Opening it adds a history entry for the match URL, so the address bar shows the match and Back closes the panel.
 * The entry keeps Livewire's own history state (its page snapshot) and the path of the page the panel was opened
 * on, so Back and Forward across wire:navigate pages restore that page first and then the panel over it, and the
 * address bar and the screen always agree. That popstate is handled here before Livewire's own handler only when
 * the entry belongs to this page.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('matchPanel', () => ({
        isOpen: false,
        requested: null,
        loaded: null,
        title: '',
        url: '',
        pushed: false,
        pageTitle: null,
        returnFocus: null,
        basePath: window.location.pathname,

        init() {
            const restored = window.history.state?.matchPanel;
            this.basePath = restored?.from ?? window.location.pathname;

            this.onPopState = (event) => {
                const entry = event.state?.matchPanel;

                if (this.pushed) {
                    // Back from the panel onto the page under it.
                    event.stopImmediatePropagation();
                    this.pushed = false;
                    this.hide();
                } else if (entry && entry.from === this.basePath) {
                    // Forward again onto a match opened over this very page.
                    event.stopImmediatePropagation();
                    this.open(entry, { fromHistory: true });
                }
                // Anything else (another page's panel included) is Livewire's: it restores that page, whose own
                // panel then reopens from the entry in init().
            };

            // Capturing listeners on the window run before Livewire's non-capturing one.
            window.addEventListener('popstate', this.onPopState, true);

            // A reload while the panel was open loads the full match page at that address: no panel over it.
            if (restored && !document.querySelector('[data-match-page]')) {
                this.$nextTick(() => this.open(restored, { fromHistory: true }));
            }
        },

        destroy() {
            window.removeEventListener('popstate', this.onPopState, true);
        },

        open({ id, title, url, pageTitle = null }, { fromHistory = false } = {}) {
            if (!this.isOpen) {
                // A restored entry knows the title of the page under it; the document shows the match's by then.
                this.pageTitle = pageTitle ?? document.title;
                this.returnFocus = document.activeElement;
            }

            this.requested = id;
            this.title = title;
            this.url = url;
            document.title = title;

            // Keep Livewire's state of the page under the panel, so its own history handling still works.
            const state = { ...(window.history.state ?? {}), matchPanel: { id, title, url, from: this.basePath, pageTitle: this.pageTitle } };

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

        close() {
            if (this.pushed) {
                window.history.back();
            } else {
                this.hide();
            }
        },

        hide() {
            this.isOpen = false;

            if (this.pageTitle !== null) {
                document.title = this.pageTitle;
            }

            this.returnFocus?.focus?.();
        },
    }));

    /** Click handler for match links: opens the match panel, but leaves new-tab and modified clicks to the browser. */
    window.Alpine.magic('openMatch', (el) => (event, id, title) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        if (document.querySelector('[data-match-panel]')) {
            window.dispatchEvent(new CustomEvent('open-match', { detail: { id, title, url: el.href } }));
        } else {
            window.Livewire.navigate(el.href);
        }
    });
});
