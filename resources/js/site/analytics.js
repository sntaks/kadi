// Analytics events (plan Phase 11): game_launch, bet_placed, slip_shared. Sent to Google Analytics only when it is
// configured (GA_MEASUREMENT_ID loads gtag in partials/head); otherwise nothing happens.
//
// Never send amounts (stakes, payouts, balances, bonuses), booking codes, references, names, phone numbers or ids:
// only the parameters listed here are passed on, and only short plain values.
const ALLOWED = {
    game_launch: ['game', 'product', 'target'],
    bet_placed: ['product', 'bet_type', 'legs', 'free_bet'],
    slip_shared: ['method'],
};

export function track(name, params = {}) {
    const keys = ALLOWED[name];

    if (!keys || typeof window.gtag !== 'function') return;

    const clean = {};
    for (const key of keys) {
        const value = params[key];
        if (typeof value === 'boolean' || (typeof value === 'number' && Number.isInteger(value) && value >= 0 && value <= 100)) {
            clean[key] = value;
        } else if (typeof value === 'string' && /^[a-z0-9_-]{1,40}$/.test(value)) {
            clean[key] = value;
        }
    }

    try {
        window.gtag('event', name, clean);
    } catch {
        // Analytics must never break the page.
    }
}

window.kadiTrack = track;

// Server-side moments (a placed bet) arrive as a Livewire browser event: $this->dispatch('kadi-track', name: ..., params: [...]).
window.addEventListener('kadi-track', (event) => track(event.detail?.name, event.detail?.params ?? {}));
