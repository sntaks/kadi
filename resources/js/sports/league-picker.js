// The sport page's league picker (<x-sports.league-picker>): filters its leagues as you type. Each result carries a
// lower-case, accent-free `data-search` (name, country, aliases such as "EPL"), and the query is normalised the same
// way, so "atletico" finds "Atlético". "/" focuses the visible picker's search, like most sites' search boxes.
const normalise = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();

document.addEventListener('alpine:init', () => {
    Alpine.data('leaguePicker', () => ({
        q: '',

        get term() {
            return normalise(this.q);
        },

        matches(el) {
            const term = this.term;

            return term === '' || term.split(' ').every((word) => el.dataset.search.includes(word));
        },

        get none() {
            const rows = this.$refs.results?.querySelectorAll('[data-search]') ?? [];

            return this.term !== '' && ![...rows].some((el) => this.matches(el));
        },

        init() {
            this.onSlash = (event) => {
                const typing = event.target.closest?.('input, textarea, select, [contenteditable="true"]');

                if (event.key !== '/' || typing || event.metaKey || event.ctrlKey || event.altKey) return;
                if (!this.$refs.search || this.$refs.search.offsetParent === null) return;

                event.preventDefault();
                this.$refs.search.focus();
            };
            window.addEventListener('keydown', this.onSlash);
        },

        destroy() {
            window.removeEventListener('keydown', this.onSlash);
        },
    }));
});
