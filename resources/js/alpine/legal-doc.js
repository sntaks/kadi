// Behaviour for /terms and /privacy (resources/views/livewire/legal/document.blade.php):
// which section you are reading, reading progress, search with in-text highlights,
// copy-a-link-to-a-section and back to top. Everything still works as plain anchors without JS.
document.addEventListener('alpine:init', () => {
    const SHEET = 'legal-contents';
    const HIGHLIGHT = 'legal-search';
    const MAX_HIGHLIGHTS = 500;

    Alpine.data('legalDoc', () => ({
        active: null,
        activeLabel: 'Jump to a section',
        progress: 0,
        showTop: false,
        query: '',
        counts: {},
        copied: null,
        sections: [],
        ticking: false,

        init() {
            this.sections = [...this.$root.querySelectorAll('[data-legal-section]')].map((el) => ({
                id: el.id,
                el,
                label: el.dataset.label,
                text: el.textContent.toLowerCase(),
            }));

            this.onScroll = () => {
                if (this.ticking) return;
                this.ticking = true;
                requestAnimationFrame(() => {
                    this.update();
                    this.ticking = false;
                });
            };
            window.addEventListener('scroll', this.onScroll, { passive: true });
            window.addEventListener('resize', this.onScroll, { passive: true });
            this.update();

            this.$watch('query', () => this.search());
            this.$watch('active', () => this.revealActive());
        },

        // Keep the section being read visible in the (scrollable) contents list.
        revealActive() {
            for (const list of this.$root.querySelectorAll('.legal-toc-list')) {
                const item = list.querySelector(`a[href="#${this.active}"]`);
                if (!item || list.clientHeight === 0) continue;

                const above = item.offsetTop < list.scrollTop;
                const below = item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight;
                if (above || below) list.scrollTop = item.offsetTop - list.clientHeight / 3;
            }
        },

        destroy() {
            window.removeEventListener('scroll', this.onScroll);
            window.removeEventListener('resize', this.onScroll);
            if (window.CSS?.highlights) CSS.highlights.delete(HIGHLIGHT);
        },

        // Section whose heading has passed under the sticky bars is the one being read.
        update() {
            const article = this.$refs.article;
            if (!article || this.sections.length === 0) return;

            const offset = window.innerWidth >= 1024 ? 140 : 170;
            let current = null;
            for (const section of this.sections) {
                if (section.el.getBoundingClientRect().top - offset > 0) break;
                current = section;
            }

            this.active = (current ?? this.sections[0]).id;
            this.activeLabel = current ? current.label : 'Jump to a section';

            const top = article.getBoundingClientRect().top + window.scrollY;
            const scrollable = article.offsetHeight - window.innerHeight;
            const read = scrollable > 0 ? (window.scrollY - top + offset) / scrollable : 0;
            this.progress = Math.round(Math.min(1, Math.max(0, read)) * 100);
            this.showTop = window.scrollY > 900;
        },

        go(id, event) {
            event?.preventDefault();
            const target = document.getElementById(id);
            if (!target) return;

            this.closeContents();
            // Let the sheet close (and give focus back) before scrolling, or the browser scrolls back.
            requestAnimationFrame(() => {
                target.scrollIntoView({ behavior: this.motion(), block: 'start' });
                history.replaceState(history.state, '', `#${id}`);
                document.getElementById(`${id}-title`)?.focus({ preventScroll: true });
            });
        },

        goToFirstMatch() {
            const first = this.sections.find((section) => this.counts[section.id] > 0);
            if (first) this.go(first.id);
        },

        openContents() {
            document.dispatchEvent(new CustomEvent('modal-show', { detail: { name: SHEET } }));
            requestAnimationFrame(() => this.revealActive());
        },

        closeContents() {
            document.dispatchEvent(new CustomEvent('modal-close', { detail: { name: SHEET } }));
        },

        toTop() {
            window.scrollTo({ top: 0, behavior: this.motion() });
        },

        motion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
        },

        async copyLink(id) {
            const url = `${location.origin}${location.pathname}#${id}`;
            try {
                await navigator.clipboard.writeText(url);
            } catch {
                // No clipboard (old browser / insecure origin): at least put the link in the address bar.
                history.replaceState(history.state, '', `#${id}`);
            }
            this.copied = id;
            clearTimeout(this.copiedTimer);
            this.copiedTimer = setTimeout(() => (this.copied = null), 2200);
        },

        // ---- Search ----------------------------------------------------------------

        get needle() {
            return this.query.trim().toLowerCase();
        },

        get searching() {
            return this.needle.length >= 2;
        },

        get resultCount() {
            return Object.values(this.counts).filter((count) => count > 0).length;
        },

        get searchSummary() {
            if (!this.searching) return '';
            const n = this.resultCount;
            if (n === 0) return `No section mentions “${this.query.trim()}”.`;
            return `${n} ${n === 1 ? 'section mentions' : 'sections mention'} “${this.query.trim()}”. Press Enter to jump to the first.`;
        },

        matches(id) {
            return !this.searching || this.counts[id] > 0;
        },

        hits(id) {
            return this.counts[id] ?? 0;
        },

        search() {
            const needle = this.needle;
            const counts = {};

            if (needle.length >= 2) {
                for (const section of this.sections) {
                    counts[section.id] = section.text.split(needle).length - 1;
                }
            }

            this.counts = counts;
            this.highlight(needle.length >= 2 ? needle : '');
        },

        // Marks matches in the text without touching the DOM (CSS Custom Highlight API, where supported).
        highlight(needle) {
            if (!window.CSS?.highlights || typeof Highlight === 'undefined') return;
            CSS.highlights.delete(HIGHLIGHT);
            if (!needle) return;

            const ranges = [];
            const walker = document.createTreeWalker(this.$refs.article, NodeFilter.SHOW_TEXT);
            while (walker.nextNode() && ranges.length < MAX_HIGHLIGHTS) {
                const node = walker.currentNode;
                const text = node.nodeValue.toLowerCase();
                let index = text.indexOf(needle);
                while (index !== -1 && ranges.length < MAX_HIGHLIGHTS) {
                    const range = new Range();
                    range.setStart(node, index);
                    range.setEnd(node, index + needle.length);
                    ranges.push(range);
                    index = text.indexOf(needle, index + needle.length);
                }
            }

            CSS.highlights.set(HIGHLIGHT, new Highlight(...ranges));
        },
    }));
});
