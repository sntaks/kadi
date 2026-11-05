// "Starts in 2 h 14 min" on the landing's big match card, kept current every 30 seconds. The wording comes from the
// server (App\Sportsbook\Support\KickoffCountdown::labels()), which also renders the first value. At kick-off the
// card's prices are disabled: they were bettable when the page was drawn, and the slip would refuse them now.
document.addEventListener('alpine:init', () => {
    Alpine.data('kickoffCountdown', (kickoff, labels) => ({
        text: '',
        timer: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 30_000);
        },

        destroy() {
            clearInterval(this.timer);
        },

        tick() {
            const seconds = Math.floor(kickoff - Date.now() / 1000);
            const left = Math.max(seconds, 0);
            const days = Math.floor(left / 86400);
            const template = seconds <= 0 ? labels.kicked
                : seconds < 60 ? labels.now
                : seconds < 3600 ? labels.minutes
                : seconds < 86400 ? labels.hours
                : days === 1 ? labels.day : labels.days;

            this.text = template
                .replace(':d', days)
                .replace(':h', Math.floor(left / 3600))
                .replace(':m', Math.floor((left % 3600) / 60));

            if (seconds <= 0) {
                this.$root.closest('.nt-bigmatch')?.querySelectorAll('.nt-odds').forEach((button) => { button.disabled = true; });
            }
        },
    }));
});
