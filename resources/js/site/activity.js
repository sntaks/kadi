// Player activity log (App\Activity\PlayerActivity): page views, clicks on elements tagged data-activity="…", and game
// closes, for staff only. Signed-in players only: the layout sets data-activity-url on <body> for them, and nothing
// runs without it. Events are queued and sent in small batches with sendBeacon (a form post carrying the CSRF token),
// so leaving the page doesn't lose them. Paths only (no query string), never typed text or amounts.
const FLUSH_MS = 5000;
const MAX_BATCH = 20;
const queue = [];
let timer = null;

function endpoint() {
    return document.body?.dataset.activityUrl || null;
}

function flush() {
    clearTimeout(timer);
    timer = null;

    const url = endpoint();
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    while (url && token && queue.length) {
        const body = new FormData();
        body.append('_token', token);
        body.append('events', JSON.stringify(queue.splice(0, MAX_BATCH)));

        try {
            navigator.sendBeacon?.(url, body) || fetch(url, { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
        } catch {
            // The activity log must never break the page.
        }
    }

    queue.length = 0;
}

export function recordActivity(name, props = {}) {
    if (!endpoint()) return;

    queue.push({ name, ...props });

    if (queue.length >= MAX_BATCH) flush();
    else timer ??= setTimeout(flush, FLUSH_MS);
}

window.kadiActivity = recordActivity;

let lastView = { path: null, at: 0 };

// The first livewire:navigated can fire on the initial load too, right after DOMContentLoaded: count a page once.
function pageView() {
    const path = location.pathname;
    if (path === lastView.path && Date.now() - lastView.at < 1500) return;

    lastView = { path, at: Date.now() };
    recordActivity('page_view', { path });
}

document.addEventListener('DOMContentLoaded', pageView);
document.addEventListener('livewire:navigated', pageView);

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target.closest('[data-activity]') : null;
    if (target) recordActivity('click', { target: target.dataset.activity, path: location.pathname });
}, { capture: true });

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') flush();
});
window.addEventListener('pagehide', flush);
document.addEventListener('livewire:navigating', flush);
