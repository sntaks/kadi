// The in-site game page (resources/views/livewire/games/play.blade.php, plan §4a).
// Posts the launch into the frame, shows a loading state, offers "Open in its own tab" when the
// game is slow, and listens to the game servers (and only them) for ready / balance / close.
document.addEventListener('alpine:init', () => {
    Alpine.data('gameFrame', ({ origins, fallbackAfter, framed, lobby }) => ({
        loaded: false,
        started: false,
        slow: false,
        error: null,
        immersive: false,
        seconds: 0,
        timers: [],

        get clock() {
            return `${Math.floor(this.seconds / 60)}:${String(this.seconds % 60).padStart(2, '0')}`;
        },

        init() {
            this.onMessage = (event) => this.message(event);
            window.addEventListener('message', this.onMessage);

            this.launch(framed ? 'game-frame' : '_self');
            requestAnimationFrame(() => (this.started = true));

            this.timers.push(setTimeout(() => { if (!this.loaded) this.slow = true; }, fallbackAfter * 1000));
            this.timers.push(setInterval(() => this.seconds++, 1000));
        },

        destroy() {
            window.removeEventListener('message', this.onMessage);
            this.timers.forEach((t) => { clearTimeout(t); clearInterval(t); });
        },

        launch(target) {
            const form = this.$refs.launch;
            form.target = target;
            form.submit();
        },

        retry() {
            this.error = null;
            this.slow = false;
            this.launch(framed ? 'game-frame' : '_self');
            this.timers.push(setTimeout(() => { if (!this.loaded) this.slow = true; }, fallbackAfter * 1000));
        },

        // Each launch issues a new single-use token, so the tab gets its own.
        openInTab() {
            this.launch('_blank');
        },

        frameLoaded() {
            let doc;
            try {
                doc = this.$refs.frame.contentDocument;
            } catch {
                doc = null;
            }

            // Cross-origin: the game itself has loaded.
            if (!doc) {
                this.loaded = true;
                return;
            }

            // Same-origin: our hand-off page (it reports its own errors), the frame's initial
            // about:blank, or anything else, such as an expired session or a server error.
            if (doc.URL === 'about:blank' || doc.querySelector('[data-handoff]')) return;

            this.error = "Something went wrong opening the game. Try again, or reload the page.";
        },

        message(event) {
            if (event.origin === window.location.origin) {
                if (event.data?.type === 'kadi-launch-error') this.error = String(event.data.message || '');
                if (event.data?.type === 'kadi-launch-used') this.error = 'This game was closed. Open it again to play.';
                return;
            }

            if (!origins.includes(event.origin) || event.source !== this.$refs.frame?.contentWindow) return;

            const type = event.data?.type;
            if (type === 'ready') this.loaded = true;
            // The balance itself still comes from the wallet webhook; this only asks for the usual refresh.
            if (type === 'balance') window.Livewire?.dispatch('wallet-refreshed');
            if (type === 'close') window.location.assign(lobby);
        },

        fullscreen() {
            const stage = this.$refs.stage;
            if (stage.requestFullscreen) {
                stage.requestFullscreen().catch(() => (this.immersive = true));
            } else {
                // iPhone Safari: no fullscreen for a frame, so the page hides its own bars instead.
                this.immersive = true;
            }
        },
    }));
});
