/**
 * "Install app" experience.
 *
 * Exposes one Alpine store, `pwaInstall`, that the install buttons and the
 * instructions dialog read from. Listeners are registered once at module
 * load (the store is created on `alpine:init`), so wire:navigate page swaps never
 * register them again and a `beforeinstallprompt` that fires before Alpine boots
 * is not lost.
 *
 * Platform behaviour:
 *   - Chromium (Android, Windows, Linux): capture `beforeinstallprompt`, call `prompt()` on click.
 *   - iOS / iPadOS: no programmatic install, so the button opens Add to Home Screen steps.
 *   - Android without the event (Firefox, some Samsung builds): browser-menu steps, offered only
 *     after a short wait so Chrome's own event gets a chance to arrive first.
 *   - Firefox desktop, macOS Safari and every other desktop case: no button.
 *   - Already installed / running standalone: no button.
 */
const SETTLE_MS = 3000;

const userAgent = () => navigator.userAgent || '';

export const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

// iPadOS 13+ reports itself as a Mac, so fall back to touch support.
export const isIOS = () =>
    /iphone|ipad|ipod/i.test(userAgent()) || (/Macintosh/i.test(userAgent()) && navigator.maxTouchPoints > 1);

export const isAndroid = () => /android/i.test(userAgent());

export const isFirefoxDesktop = () => /firefox/i.test(userAgent()) && !isAndroid() && !isIOS();

let deferredPrompt = null;
let started = false;

// Values seen before Alpine created the store are folded in when it does.
const pending = {
    standalone: false,
    installed: false,
    canPrompt: false,
    settled: false,
    announcement: '',
};

function update(patch) {
    const store = window.Alpine && window.Alpine.store('pwaInstall');

    Object.assign(store || pending, patch);
}

function createStore() {
    return {
        ...pending,

        /** 'prompt' | 'ios' | 'manual' | null (null means: show nothing). */
        get mode() {
            if (this.standalone || this.installed || isFirefoxDesktop()) return null;
            if (this.canPrompt) return 'prompt';
            if (isIOS()) return 'ios';
            if (isAndroid() && this.settled) return 'manual';

            return null;
        },

        get visible() {
            return this.mode !== null;
        },

        activate() {
            if (this.mode === 'prompt') {
                return this.promptInstall();
            }

            if (this.mode) {
                window.dispatchEvent(new CustomEvent('pwa-install-help', { detail: { mode: this.mode } }));
            }
        },

        async promptInstall() {
            const prompt = deferredPrompt;

            if (!prompt) return;

            // A captured event can only be used once.
            deferredPrompt = null;
            this.canPrompt = false;

            try {
                await prompt.prompt();
                const choice = await prompt.userChoice;

                console.debug('[pwa] install prompt outcome:', choice.outcome);

                if (choice.outcome === 'accepted') {
                    this.installed = true;
                    this.announcement = 'Kadi is being added to your device.';
                }
            } catch (e) {
                console.debug('[pwa] install prompt failed', e);
            }
        },
    };
}

export function initInstall() {
    if (started) return;
    started = true;

    pending.standalone = isStandalone();

    document.addEventListener('alpine:init', () => {
        window.Alpine.store('pwaInstall', createStore());
    });

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        update({ canPrompt: true, settled: true });
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        update({ canPrompt: false, installed: true, announcement: 'Kadi was added to your device.' });
    });

    const displayMode = window.matchMedia('(display-mode: standalone)');
    const onDisplayMode = () => update({ standalone: isStandalone() });

    if (displayMode.addEventListener) {
        displayMode.addEventListener('change', onDisplayMode);
    }

    window.setTimeout(() => update({ settled: true }), SETTLE_MS);
}
