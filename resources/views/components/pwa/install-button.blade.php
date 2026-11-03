{{--
    "Install app" entry points. Every variant is driven by the `pwaInstall` Alpine store
    (resources/js/pwa/install.js), which decides whether anything should show at all, so
    each one is display:none (and takes no space) unless the browser can install.

      nav     sidebar item in layouts/app.blade.php. Uses the sidebar's Alpine scope
              (showLabels(), expanded).
      banner  dismissible card (dashboard). Dismissal is remembered for 14 days.
      chip    compact pill for a page's title row or a nav cluster. Adds no height.
      hero    outline button sized to sit beside a hero's primary button. Phones/tablets only.
      menu    row for a dropdown menu. Uses the menu's Alpine scope (menuOpen) to close it.
      shell   row for the site shell's account menu and drawer (layouts/site.blade.php).

    `wire:ignore` on each root stops Livewire re-renders from resetting the inline
    display that Alpine's x-show manages, which would make the button vanish.

    Pair with <x-pwa.install-dialog /> (once per page) for the iOS / manual instructions.
--}}
@props(['variant' => 'nav'])

@if ($variant === 'banner')
    <div
        wire:ignore
        x-show="$store.pwaInstall.showBanner"
        style="display: none;"
        data-test="pwa-install-banner"
        class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-4 sm:flex-nowrap"
    >
        {{-- Icon + copy take the full first row on phones; the buttons drop to a second row. --}}
        <div class="flex min-w-0 basis-full items-center gap-4 sm:flex-1 sm:basis-auto">
            <img src="/pwa-icons/icon-192.png" alt="" width="44" height="44" class="h-11 w-11 shrink-0 rounded-lg">

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-[#f5f5f0]">Install Kadi</p>
                <p class="text-xs text-[#6b6b6b]">Get one-tap access from your home screen or desktop.</p>
            </div>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-2">
            <button
                type="button"
                @click="$store.pwaInstall.activate()"
                :aria-haspopup="$store.pwaInstall.mode === 'prompt' ? null : 'dialog'"
                class="btn-casino-primary rounded-full px-5 py-2 text-sm"
            >
                Install
            </button>

            <button
                type="button"
                @click="$store.pwaInstall.dismissBanner()"
                aria-label="Dismiss install suggestion"
                class="rounded-md p-1.5 text-gray-500 transition hover:text-[#f5f5f0] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#f5c542]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    </div>
@elseif ($variant === 'chip')
    <button
        type="button"
        wire:ignore
        x-show="$store.pwaInstall.visible"
        style="display: none;"
        data-test="pwa-install-chip"
        @click="$store.pwaInstall.activate()"
        :aria-haspopup="$store.pwaInstall.mode === 'prompt' ? null : 'dialog'"
        {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[#f5c542]/30 bg-[#f5c542]/5 px-3 py-1.5 text-xs font-semibold text-[#f5c542] transition hover:border-[#f5c542]/60 hover:bg-[#f5c542]/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5c542]']) }}
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        <span>Install app</span>
    </button>
@elseif ($variant === 'hero')
    {{-- Sits beside the hero's primary button on phones/tablets; same height, quieter (outline, not fill). --}}
    <button
        type="button"
        wire:ignore
        x-show="$store.pwaInstall.visible"
        style="display: none;"
        data-test="pwa-install-hero"
        aria-label="Install app"
        @click="$store.pwaInstall.activate()"
        :aria-haspopup="$store.pwaInstall.mode === 'prompt' ? null : 'dialog'"
        class="font-cinzel inline-flex min-h-12 min-w-12 items-center justify-center gap-2 rounded-full px-3.5 py-3.5 text-sm font-bold text-[#f5c542] ring-1 ring-inset ring-[#f5c542]/40 transition-all duration-300 hover:bg-[#f5c542]/10 hover:ring-[#f5c542]/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5c542] min-[360px]:px-5 md:hidden"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        {{-- Icon-only on very narrow phones so the row never wraps and Play never moves. --}}
        <span class="hidden min-[360px]:inline">Install</span>
    </button>
@elseif ($variant === 'menu')
    <button
        type="button"
        wire:ignore
        x-show="$store.pwaInstall.visible"
        style="display: none;"
        data-test="pwa-install-menu"
        @click="menuOpen = false; $store.pwaInstall.activate()"
        :aria-haspopup="$store.pwaInstall.mode === 'prompt' ? null : 'dialog'"
        class="flex w-full items-center gap-2 py-3 text-left text-sm text-[#f5f5f0]/70 transition hover:text-[#f5c542]"
    >
        <svg class="h-4 w-4 text-[#f5c542]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        <span>Install app</span>
    </button>
@elseif ($variant === 'shell')
    <button
        type="button"
        wire:ignore
        x-show="$store.pwaInstall.visible"
        style="display: none;"
        data-test="pwa-install-shell"
        @click="$store.pwaInstall.activate()"
        :aria-haspopup="$store.pwaInstall.mode === 'prompt' ? null : 'dialog'"
        {{ $attributes->class('nt-mi') }}
    >
        <x-site.icon name="down" />
        <span>Install the app</span>
    </button>
@else
    <button
        type="button"
        x-show="$store.pwaInstall.visible"
        style="display: none;"
        data-test="pwa-install-nav"
        @click="$store.pwaInstall.activate()"
        :aria-haspopup="$store.pwaInstall.mode === 'prompt' ? null : 'dialog'"
        :class="showLabels() ? 'justify-start' : 'justify-center px-0'"
        :title="!expanded ? 'Install app' : ''"
        class="flex w-full items-center gap-3 rounded-lg border-l-2 border-transparent px-3 py-2.5 text-sm text-gray-400 transition-all hover:bg-[#161616] hover:text-white"
    >
        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
        </svg>
        <span
            x-show="showLabels()"
            x-transition:enter="transition-opacity duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="text-sm font-medium whitespace-nowrap"
        >Install app</span>
    </button>
@endif
