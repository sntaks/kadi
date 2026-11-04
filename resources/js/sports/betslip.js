/**
 * Bet slip picks, kept in the browser so the slip survives reloads and page changes (plan §5.6).
 *
 * The store only remembers what the player picked, the price they saw and the labels to show for it. The slip shows
 * a new pick from these at once; the bet slip Livewire component then checks every pick against current prices on
 * the server whenever the picks change, and only its answer counts.
 */
const STORAGE_KEY = 'kadi.betslip.v1';

function loadPicks() {
    try {
        const picks = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '[]');

        return Array.isArray(picks) ? picks.filter((pick) => pick && Number.isInteger(pick.selection_id)) : [];
    } catch {
        return [];
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.store('betslip', {
        picks: loadPicks(),
        maxPicks: Number(document.querySelector('meta[name="kadi-max-selections"]')?.content) || 30,

        /** The slip as a drawer or bottom sheet, below the width where it sits beside the page. */
        isOpen: false,

        has(selectionId) {
            return this.picks.some((pick) => pick.selection_id === selectionId);
        },

        count() {
            return this.picks.length;
        },

        /** The combined odds of the prices the player saw, rounded down to 2 decimals as the server does. */
        seenOdds() {
            const odds = this.picks.reduce((product, pick) => product * Number(pick.price), 1);

            return (Math.floor(Number((odds * 100).toFixed(6))) / 100).toFixed(2);
        },

        /** Add a pick, replace the pick from the same match, or remove it when it is already on the slip. */
        toggle(fixtureId, selectionId, price, labels = {}) {
            if (this.has(selectionId)) {
                this.remove(selectionId);

                return;
            }

            const others = this.picks.filter((pick) => pick.fixture_id !== fixtureId);

            if (others.length >= this.maxPicks) {
                window.dispatchEvent(new CustomEvent('betslip-full', { detail: { max: this.maxPicks } }));

                return;
            }

            this.replace([...others, { fixture_id: fixtureId, selection_id: selectionId, price: String(price), ...labels }]);
        },

        remove(selectionId) {
            this.replace(this.picks.filter((pick) => pick.selection_id !== selectionId));
        },

        clear() {
            this.replace([]);
        },

        /** Take the prices the server offers now, after the player accepts the changes. */
        acceptPrices(prices) {
            this.replace(this.picks.map((pick) => (prices[pick.selection_id] ? { ...pick, price: prices[pick.selection_id] } : pick)));
        },

        replace(picks) {
            this.picks = picks.slice(0, this.maxPicks);

            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(this.picks));
            } catch {
                // Private browsing can block storage; the slip still works for this page view.
            }

            window.dispatchEvent(new CustomEvent('betslip-changed', { detail: { picks: this.picks } }));
        },
    });

    // The bottom bar's Bet slip button and the floating pill open the slip as a sheet.
    window.addEventListener('open-bet-slip', () => {
        window.Alpine.store('betslip').isOpen = true;
    });

    // Another tab changed the slip: follow it, so two tabs never place different slips by surprise.
    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY) {
            const store = window.Alpine.store('betslip');
            store.picks = loadPicks();
            window.dispatchEvent(new CustomEvent('betslip-changed', { detail: { picks: store.picks } }));
        }
    });

    /**
     * The labels the slip shows for a pick before the server answers, read from the odds button and the market and
     * match around it.
     */
    window.Alpine.magic('pickLabels', (el) => ({
        name: el.dataset.pickName ?? '',
        market: el.closest('[data-market]')?.dataset.market ?? '',
        match: el.closest('[data-match]')?.dataset.match ?? '',
    }));
});
