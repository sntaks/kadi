// Landing hero carousel (components/lobby/hero.blade.php, styles in resources/css/hero.css), at every width. Below
// 768px the big match slide ([data-below]) leaves the rotation and shows under the carousel instead.
//
// There are no visible controls at any width. The timer is an invisible element ([data-hero-clock]): a CSS animation
// of --hero-dur whose animationend moves to the next slide, so pausing is animation-play-state and there is no interval
// to drift. Autoplay pauses while the pointer or focus is on the slides or the tab is hidden, and stops for good once
// the player swipes, uses the arrow keys or taps anything in a slide. With reduced motion it never autoplays.
//
// The server renders slide 1 active and the rest hidden + inert. Armed, this script drops `hidden` (idle slides stay in
// the layout, invisible, so the stage keeps the tallest slide's height) and owns data-state, inert and data-enter. The
// root and slides are wire:ignore.self, so a Livewire re-render keeps the current slide.
// Slide images carry data-src/data-srcset and load only when their slide is about to show (warm()). Autoplay also
// pauses while the carousel is scrolled out of view.
const FOCUSABLE = 'a, button, input, select, textarea, [tabindex]';

document.addEventListener('alpine:init', () => {
    Alpine.data('heroSlider', () => ({
        index: 0,
        stopped: false,
        armed: false,
        moving: false,
        holds: new Set(),
        swipe: null,
        frame: 0,

        init() {
            this.wide = window.matchMedia('(min-width: 768px)');
            this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
            this.onWide = () => this.layout();
            this.onVisibility = () => this.hold('hidden', document.hidden);
            this.wide.addEventListener('change', this.onWide);
            document.addEventListener('visibilitychange', this.onVisibility);

            const root = this.$root;
            const stage = root.querySelector('[data-hero-stage]');
            root.addEventListener('click', (e) => this.onClick(e));
            root.addEventListener('keydown', (e) => this.onKey(e));
            root.addEventListener('animationend', (e) => {
                if (e.animationName === 'nt-hero-timer' && !this.stopped) this.next();
            });
            stage?.addEventListener('mouseenter', () => this.hold('hover', true));
            stage?.addEventListener('mouseleave', () => { this.hold('hover', false); this.parallax(null); });
            stage?.addEventListener('focusin', () => this.hold('focus', true));
            stage?.addEventListener('focusout', (e) => this.hold('focus', stage.contains(e.relatedTarget)));
            stage?.addEventListener('pointerdown', (e) => this.onPointerDown(e));
            stage?.addEventListener('pointerup', (e) => this.onPointerUp(e));
            stage?.addEventListener('pointercancel', () => { this.swipe = null; });
            stage?.addEventListener('pointermove', (e) => this.parallax(e));

            if ('IntersectionObserver' in window) {
                this.seen = new IntersectionObserver(([entry]) => this.hold('offscreen', !entry.isIntersecting), { threshold: 0.25 });
                this.seen.observe(root);
            }

            this.arm();
        },

        destroy() {
            this.wide.removeEventListener('change', this.onWide);
            document.removeEventListener('visibilitychange', this.onVisibility);
            this.seen?.disconnect();
            clearTimeout(this.settle);
            clearTimeout(this.done);
            cancelAnimationFrame(this.frame);
        },

        slides() { return [...this.$root.querySelectorAll('[data-slide]')]; },

        // The install slide only counts on a device that can install the app; the big match slide only from 768px.
        available(slide) {
            return !!slide
                && (!slide.hasAttribute('data-requires-install') || !!window.Alpine.store('pwaInstall')?.visible)
                && !(slide.hasAttribute('data-below') && this.below());
        },

        // Phones: the big match sits under the carousel, visible and usable, outside the rotation.
        below() { return !this.wide.matches; },

        order() { return this.slides().flatMap((s, i) => (this.available(s) ? [i] : [])); },

        step(by) {
            const order = this.order();
            const at = Math.max(order.indexOf(this.index), 0);

            return order[(at + by + order.length) % order.length];
        },

        arm() {
            if (this.armed || this.slides().length < 2) return;

            this.armed = true;
            this.stopped = this.stopped || this.reduced.matches;
            // Every slide keeps its space from now on (idle ones are invisible and inert, hero.css), so the stage is
            // always as tall as the tallest slide.
            this.slides().forEach((slide, i) => this.rest(slide, i === this.index));
            this.$root.classList.add('is-armed');
            this.sync();
            this.warm(this.slides()[this.step(1)]);
            this.startTimer();
            // The install store settles a few seconds after load (pwa/install.js): look again then.
            this.settle = setTimeout(() => this.sync(), 3500);
        },

        // Crossing 768px: the big match joins or leaves the rotation. Leaving it while on it goes to the first slide.
        layout() {
            if (!this.armed) return;

            const slides = this.slides();
            if (!this.available(slides[this.index])) {
                clearTimeout(this.done);
                this.moving = false;
                this.$root.classList.remove('is-moving');
                this.index = this.order()[0] ?? 0;
            }

            slides.forEach((slide, i) => this.rest(slide, i === this.index));
            this.sync();
            this.startTimer();
        },

        rest(slide, active) {
            const below = slide.hasAttribute('data-below') && this.below();

            slide.dataset.state = below ? 'below' : active ? 'active' : 'idle';
            slide.hidden = !active && !below && !this.armed;
            slide.inert = !active && !below;
            slide.removeAttribute('data-enter');
        },

        // Slide labels follow availability; with fewer than two slides left there is no timer.
        sync() {
            const slides = this.slides();
            const order = this.order();

            order.forEach((i, at) => slides[i].setAttribute('aria-label', `${at + 1} of ${order.length}: ${slides[i].dataset.label}`));

            // Fewer than two slides in the rotation (a phone with no promotions on): no timer.
            const few = order.length < 2;
            this.few = few;
            if (few) this.$root.classList.remove('is-timing');

            if (!this.available(slides[this.index])) this.go(order[0] ?? 0, 'next');
        },

        startTimer() {
            const root = this.$root;
            const slide = this.slides()[this.index];

            root.classList.remove('is-timing');
            if (this.stopped || !this.armed || this.few) return;

            root.style.setProperty('--hero-dur', `${Number(slide?.dataset.duration) || 7000}ms`);
            void root.offsetWidth; // restart the bar animation
            root.classList.add('is-timing');
        },

        hold(reason, on) {
            on ? this.holds.add(reason) : this.holds.delete(reason);
            this.$root.classList.toggle('is-paused', this.holds.size > 0);
        },

        stop() {
            this.stopped = true;
            this.$root.classList.remove('is-timing');
        },

        next() { this.go(this.step(1), 'next'); },
        prev() { this.go(this.step(-1), 'prev'); },

        go(i, dir) {
            const slides = this.slides();
            const from = slides[this.index];
            const to = slides[i];

            if (!this.armed || this.moving || i === this.index || !this.available(to)) return;

            const root = this.$root;
            const reduced = this.reduced.matches;

            this.moving = true;
            root.classList.add('is-moving');
            root.dataset.dir = dir;
            this.warm(to);
            this.split(to);

            to.style.setProperty('--ox', dir === 'prev' ? '15%' : '85%');
            to.hidden = false;
            to.inert = false;
            to.removeAttribute('data-enter');
            void to.offsetWidth; // replay the entrances on a slide seen before
            to.setAttribute('data-enter', '');
            to.dataset.state = 'in';

            if (from) {
                from.dataset.state = 'out';
                from.inert = true;
            }

            this.index = i;
            this.sync();
            this.announce(to);
            this.startTimer();
            this.warm(slides[this.step(1)]);

            this.done = setTimeout(() => {
                if (from && from !== to) this.rest(from, false);
                to.dataset.state = 'active';
                this.moving = false;
                root.classList.remove('is-moving');
            }, reduced ? 170 : 950);
        },

        // Slide images are not requested until their slide is next or showing (idle slides sit in the viewport, so
        // native lazy loading would fetch them all at once).
        warm(slide) {
            slide?.querySelectorAll('img[data-srcset]').forEach((img) => {
                img.sizes = img.dataset.sizes || '';
                img.srcset = img.dataset.srcset;
                img.src = img.dataset.src;
                img.removeAttribute('data-srcset');
            });
        },

        // Titles rise word by word: each word in a clipping mask. The full text stays the accessible name.
        split(slide) {
            slide.querySelectorAll('[data-split]').forEach((title) => {
                if (title.querySelector('.nt-w')) return;

                const words = title.textContent.trim().split(/\s+/);
                title.setAttribute('aria-label', words.join(' '));
                title.replaceChildren(...words.flatMap((word, n) => {
                    const mask = document.createElement('span');
                    const inner = document.createElement('span');
                    mask.className = 'nt-w';
                    mask.setAttribute('aria-hidden', 'true');
                    inner.style.setProperty('--w', n);
                    inner.textContent = word;
                    mask.append(inner);

                    return n ? [document.createTextNode(' '), mask] : [mask];
                }));
            });
        },

        // While autoplaying nothing is announced; after the player takes over, the new slide is.
        announce(slide) {
            const live = this.$root.querySelector('[data-hero-live]');
            if (live) live.textContent = this.stopped ? slide.getAttribute('aria-label') : '';
        },

        onClick(e) {
            if (e.target.closest('[data-hero-install]')) window.Alpine.store('pwaInstall')?.activate();
        },

        onKey(e) {
            if (!this.armed || !['ArrowLeft', 'ArrowRight'].includes(e.key) || e.target.closest('input, select, textarea')) return;

            e.preventDefault();
            this.stop();
            e.key === 'ArrowLeft' ? this.prev() : this.next();
        },

        onPointerDown(e) {
            if (!this.armed) return;

            // A tap on a price, a link or Play Kadi: the player is busy with this slide. Only controls inside the
            // slides count: an ancestor such as <main tabindex="-1"> must not turn every touch into a tap.
            const hit = e.target.closest(FOCUSABLE);
            if (hit && e.currentTarget.contains(hit)) {
                this.stop();
                this.swipe = null;

                return;
            }

            this.swipe = { x: e.clientX, y: e.clientY };
        },

        onPointerUp(e) {
            if (!this.swipe) return;

            const dx = e.clientX - this.swipe.x;
            const dy = e.clientY - this.swipe.y;
            this.swipe = null;

            if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy)) return;

            this.stop();
            dx < 0 ? this.next() : this.prev();
        },

        parallax(e) {
            if (!this.armed || !this.finePointer.matches || this.reduced.matches) return;

            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => {
                const slide = this.slides()[this.index];
                if (!slide) return;

                const box = this.$root.querySelector('[data-hero-stage]').getBoundingClientRect();
                const px = e ? ((e.clientX - box.left) / box.width) * 2 - 1 : 0;
                const py = e ? ((e.clientY - box.top) / box.height) * 2 - 1 : 0;
                slide.style.setProperty('--px', px.toFixed(3));
                slide.style.setProperty('--py', py.toFixed(3));
            });
        },
    }));
});
