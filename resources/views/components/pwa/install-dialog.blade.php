{{--
    Instructions dialog for platforms that cannot show a native install prompt, plus an
    aria-live region that announces install progress. Render once per page.

    Opened by the `pwa-install-help` window event ({ detail: { mode: 'ios' | 'manual' } }),
    which the `pwaInstall` store dispatches. A native <dialog> gives us focus trapping,
    Esc to close and focus restoration to the button that opened it.
--}}
<div
    x-data="{
        mode: null,
        show(mode) {
            this.mode = mode;
            this.$nextTick(() => this.$refs.dialog.showModal());
        },
        close() {
            this.$refs.dialog.close();
        },
    }"
    x-on:pwa-install-help.window="show($event.detail.mode)"
>
    <div class="sr-only" role="status" aria-live="polite" x-text="$store.pwaInstall.announcement"></div>

    <dialog
        x-ref="dialog"
        id="pwa-install-dialog"
        aria-labelledby="pwa-install-title"
        @close="mode = null"
        @click="if ($event.target === $refs.dialog) close()"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-gold/30 bg-felt p-0 text-ivory shadow-2xl backdrop:bg-black/70 backdrop:backdrop-blur-sm"
    >
        <div class="p-6">
            <div class="mb-5 flex items-center gap-3">
                <img src="/pwa-icons/icon-192.png" alt="" width="40" height="40" class="h-10 w-10 rounded-lg">
                <h2 id="pwa-install-title" class="text-lg font-bold text-gold">
                    <span x-show="mode === 'ios'">Add Kadi to your Home Screen</span>
                    <span x-show="mode === 'manual'">Install Kadi</span>
                </h2>
            </div>

            {{-- iPhone / iPad --}}
            <ol x-show="mode === 'ios'" class="space-y-4 text-sm text-ivory/85">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gold text-xs font-bold text-black">1</span>
                    <span class="flex flex-wrap items-center gap-1.5">
                        Tap the Share button
                        <svg class="inline h-5 w-5 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" role="img" aria-label="Share">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0-12l-4 4m4-4l4 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                        </svg>
                        in the browser toolbar.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gold text-xs font-bold text-black">2</span>
                    <span>Scroll down and choose <strong class="text-ivory">Add to Home Screen</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gold text-xs font-bold text-black">3</span>
                    <span>Tap <strong class="text-ivory">Add</strong>, then open Kadi from your Home Screen.</span>
                </li>
            </ol>

            {{-- Android browsers without a native prompt --}}
            <ol x-show="mode === 'manual'" class="space-y-4 text-sm text-ivory/85">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gold text-xs font-bold text-black">1</span>
                    <span>Open your browser menu (the <strong class="text-ivory">&#8942;</strong> or <strong class="text-ivory">&#8801;</strong> button).</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gold text-xs font-bold text-black">2</span>
                    <span>Choose <strong class="text-ivory">Install app</strong> or <strong class="text-ivory">Add to Home screen</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gold text-xs font-bold text-black">3</span>
                    <span>Confirm, then open Kadi from your home screen.</span>
                </li>
            </ol>

            <p x-show="mode === 'ios'" class="mt-5 rounded-lg border border-gold/20 bg-gold/5 p-3 text-xs text-ivory/70">
                Notifications become available once Kadi is opened from your Home Screen.
            </p>

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    autofocus
                    @click="close()"
                    class="nt-btn nt-btn-gold nt-btn-sm"
                >
                    Got it
                </button>
            </div>
        </div>
    </dialog>
</div>
