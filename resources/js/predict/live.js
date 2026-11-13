// Live prediction market figures (plan Phase 3). The feed worker (App\Predict\MarketFeed) broadcasts every market
// whose shown state moved on the public `predict.markets` channel; this patches the chance, the multiplier, the
// side buttons, the status line and the chart's last point in place, without a Livewire round trip. The pages'
// own 25 s poll re-renders from the server and corrects anything missed. Only pages marked [data-predict-live]
// listen, so other pages hold no subscription.

const CHANNEL = 'predict.markets';
let subscribed = false;

export function percent(probability) {
    if (typeof probability !== 'number' || Number.isNaN(probability)) return '–';

    return `${Math.round(Math.min(1, Math.max(0, probability)) * 100)}%`;
}

// A multiplier that pays more than the stake, or null (the API cannot price the side, or it would pay less).
export function playable(multiplier) {
    return typeof multiplier === 'number' && multiplier > 1 ? multiplier : null;
}

export function reason(market) {
    if (market.status === 'suspended') return 'Betting paused';
    if (market.status === 'voided') return 'Voided: stakes refunded';
    if (market.status === 'resolved' || market.status === 'settled') return 'Result being confirmed';
    if (market.status !== 'open' || !market.betting_available) return 'Betting closed';

    return null;
}

function patchSide(root, market, side) {
    const odds = market.odds ?? {};

    root.querySelectorAll(`[data-predict-chance="${side}"]`).forEach((el) => {
        el.textContent = percent(odds[`${side}_probability`]);
    });

    if (side === 'yes') {
        root.querySelectorAll('[data-predict-gauge-fill]').forEach((el) => {
            const p = odds.yes_probability;
            el.setAttribute('stroke-dasharray', `${typeof p === 'number' ? Math.round(Math.min(1, Math.max(0, p)) * 100) : 0} 100`);
        });
    }

    const multiplier = playable(odds[`${side}_multiplier`]);
    const open = reason(market) === null && multiplier !== null;

    root.querySelectorAll(`[data-predict-side="${side}"]`).forEach((el) => {
        const mult = el.querySelector('[data-predict-mult]');
        const raw = odds[`${side}_multiplier`];
        // Priced, but a win would pay back no more than the stake: say so instead of a blank.
        const below = typeof raw === 'number' && raw > 0 && raw <= 1;
        if (mult) mult.textContent = multiplier !== null ? `${multiplier.toFixed(2)}x` : (below ? (el.dataset.belowText ?? '–') : '–');

        if (el.tagName === 'BUTTON') {
            el.disabled = !open || el.dataset.canBet !== '1';
        } else {
            el.classList.toggle('is-off', !open);
            open ? el.removeAttribute('aria-disabled') : el.setAttribute('aria-disabled', 'true');
        }
    });
}

function patchStatus(root, market) {
    root.querySelectorAll('[data-predict-status]').forEach((el) => {
        const why = reason(market);
        el.textContent = why ?? el.dataset.openText ?? el.textContent;
        el.classList.toggle('nt-pm-reason', why !== null);
    });
}

function patchChart(market) {
    const yes = market.odds?.yes_probability;
    if (typeof yes !== 'number') return;

    document.querySelectorAll(`[data-predict-chart][data-market="${market.id}"]`).forEach((chart) => {
        const height = Number(chart.dataset.height) || 200;
        const y = Math.round((1 - Math.min(1, Math.max(0, yes))) * height * 10) / 10;
        const line = chart.querySelector('[data-predict-line]');
        const dot = chart.querySelector('[data-predict-dot]');

        if (line) {
            const points = line.getAttribute('points').trim().split(/\s+/);
            const [x] = points[points.length - 1].split(',');
            points[points.length - 1] = `${x},${y}`;
            line.setAttribute('points', points.join(' '));

            const area = chart.querySelector('.nt-pm-area');
            const width = Number(chart.querySelector('svg')?.viewBox.baseVal.width) || 600;
            if (area) area.setAttribute('points', `0,${height} ${points.join(' ')} ${width},${height}`);
        }

        if (dot) dot.style.top = `${(y / height) * 100}%`;
    });
}

export function apply(market) {
    if (!market || typeof market.id !== 'number') return;

    document.querySelectorAll(`[data-predict-market="${market.id}"]`).forEach((root) => {
        patchSide(root, market, 'yes');
        patchSide(root, market, 'no');
        patchStatus(root, market);
    });

    patchChart(market);

    // The slip listens: a market that stops taking bets drops its open price at once.
    window.dispatchEvent(new CustomEvent('predict-market-live', { detail: market }));
}

function sync() {
    const echo = window.Echo;
    if (!echo) return;

    const wanted = document.querySelector('[data-predict-live]') !== null;

    if (wanted && !subscribed) {
        echo.channel(CHANNEL).listen('.markets.changed', (event) => (event.markets ?? []).forEach(apply));
        subscribed = true;
    } else if (!wanted && subscribed) {
        echo.leaveChannel(CHANNEL);
        subscribed = false;
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', sync);
    document.addEventListener('livewire:navigated', sync);
}
