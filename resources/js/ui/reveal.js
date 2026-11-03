// Night table: sections marked .nt-reveal fade up as they scroll into view.
// Only sections still below the fold are armed (hidden), and only by this script: nothing already on
// screen blinks out, and without JavaScript nothing is hidden. Sections Livewire adds later are caught
// by a MutationObserver; everything is reset on each wire:navigate.
let intersections = null;
let mutations = null;

function arm(el) {
    if (el.classList.contains('nt-armed') || el.classList.contains('nt-in-view')) return;

    if (el.getBoundingClientRect().top < window.innerHeight) {
        el.classList.add('nt-in-view');
        return;
    }

    el.classList.add('nt-armed');
    intersections.observe(el);
}

function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches('.nt-reveal')) arm(root);
    root.querySelectorAll('.nt-reveal').forEach(arm);
}

function start() {
    if (!('IntersectionObserver' in window) || !('MutationObserver' in window)) return;

    intersections = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('nt-in-view');
                intersections.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    mutations = new MutationObserver((records) => records.forEach((r) => r.addedNodes.forEach(scan)));
    mutations.observe(document.body, { childList: true, subtree: true });

    scan(document.body);
}

function stop() {
    intersections?.disconnect();
    mutations?.disconnect();
    intersections = mutations = null;
}

// livewire:navigated fires on the first load too.
document.addEventListener('livewire:navigated', () => { stop(); start(); });
document.addEventListener('livewire:navigating', stop);
