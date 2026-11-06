// The Kadi prize pool's count-up (components/lobby/kadi-table.blade.php), as on the old site: the first time the
// panel is mostly in view, every [data-count-to] amount runs from 0 to its figure with an ease-out. The real figure is
// already in the HTML, so without JavaScript, or with reduced motion, nothing moves. While counting, each amount keeps
// its final width (no layout shift) and its final text as the accessible name.
const DURATION = 2200;
const format = new Intl.NumberFormat('en-US');

document.addEventListener('alpine:init', () => {
    Alpine.data('prizeCount', () => ({
        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;

            this.seen = new IntersectionObserver(([entry]) => {
                if (!entry.isIntersecting) return;

                this.seen.disconnect();
                this.run();
            }, { threshold: 0.5 });
            this.seen.observe(this.$el);
        },

        destroy() {
            this.seen?.disconnect();
            cancelAnimationFrame(this.frame);
        },

        run() {
            const amounts = [...this.$el.querySelectorAll('[data-count-to]')].map((el) => {
                const final = el.textContent.trim();
                el.style.minWidth = `${el.getBoundingClientRect().width}px`;
                el.style.textAlign = 'right';
                el.setAttribute('aria-label', final);

                return { el, final, to: Number(el.dataset.countTo) || 0 };
            });
            const start = performance.now();

            const tick = (now) => {
                const t = Math.min((now - start) / DURATION, 1);
                const eased = 1 - (1 - t) ** 3;

                amounts.forEach(({ el, final, to }) => {
                    el.textContent = t < 1 ? `KSh ${format.format(Math.round(to * eased))}` : final;
                });

                if (t < 1) {
                    this.frame = requestAnimationFrame(tick);
                } else {
                    amounts.forEach(({ el }) => el.removeAttribute('aria-label'));
                }
            };

            this.frame = requestAnimationFrame(tick);
        },
    }));
});
