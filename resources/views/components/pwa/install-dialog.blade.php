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
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-[#f5c542]/30 bg-[#141414] p-0 text-[#f5f5f0] shadow-2xl backdrop:bg-black/70 backdrop:backdrop-blur-sm"
    >
        <div class="p-6">
            <div class="mb-5 flex items-center gap-3">
                <img src="/pwa-icons/icon-192.png" alt="" width="40" height="40" class="h-10 w-10 rounded-lg">
                <h2 id="pwa-install-title" class="text-lg font-bold text-[#f5c542]" @unless (config('platform.ui_v2')) style="font-family: 'Cinzel', serif;" @endunless>
                    <span x-show="mode === 'ios'">Add Kadi to your Home Screen</span>
                    <span x-show="mode === 'manual'">Install Kadi</span>
                </h2>
            </div>

            {{-- iPhone / iPad --}}
            <ol x-show="mode === 'ios'" class="space-y-4 text-sm text-[#f5f5f0]/85">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f5c542] text-xs font-bold text-black">1</span>
                    <span class="flex flex-wrap items-center gap-1.5">
                        Tap the Share button
                        <svg class="inline h-5 w-5 text-[#f5c542]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" role="img" aria-label="Share">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0-12l-4 4m4-4l4 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                        </svg>
                        in the browser toolbar.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f5c542] text-xs font-bold text-black">2</span>
                    <span>Scroll down and choose <strong class="text-[#f5f5f0]">Add to Home Screen</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f5c542] text-xs font-bold text-black">3</span>
                    <span>Tap <strong class="text-[#f5f5f0]">Add</strong>, then open Kadi from your Home Screen.</span>
                </li>
            </ol>

            {{-- Android browsers without a native prompt --}}
            <ol x-show="mode === 'manual'" class="space-y-4 text-sm text-[#f5f5f0]/85">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f5c542] text-xs font-bold text-black">1</span>
                    <span>Open your browser menu (the <strong class="text-[#f5f5f0]">&#8942;</strong> or <strong class="text-[#f5f5f0]">&#8801;</strong> button).</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f5c542] text-xs font-bold text-black">2</span>
                    <span>Choose <strong class="text-[#f5f5f0]">Install app</strong> or <strong class="text-[#f5f5f0]">Add to Home screen</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#f5c542] text-xs font-bold text-black">3</span>
                    <span>Confirm, then open Kadi from your home screen.</span>
                </li>
            </ol>

            <p x-show="mode === 'ios'" class="mt-5 rounded-lg border border-[#f5c542]/20 bg-[#f5c542]/5 p-3 text-xs text-[#f5f5f0]/70">
                Notifications become available once Kadi is opened from your Home Screen.
            </p>

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    autofocus
                    @click="close()"
                    class="btn-casino-primary rounded-full px-6 py-2 text-sm"
                >
                    Got it
                </button>
            </div>
        </div>
    </dialog>
</div>
