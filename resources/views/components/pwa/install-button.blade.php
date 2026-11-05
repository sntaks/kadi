{{--
    "Install app" entry points. Each is driven by the `pwaInstall` Alpine store (resources/js/pwa/install.js), which
    decides whether anything should show at all, so each one is display:none (and takes no space) unless the browser
    can install.

      chip   compact button for a page's title row (the wallet). Adds no height.
      shell  row for the site shell's account menu and drawer (layouts/site.blade.php).

    `wire:ignore` on each root stops Livewire re-renders from resetting the inline display that Alpine's x-show
    manages, which would make the button vanish. Pair with <x-pwa.install-dialog /> (once per page, in the shell)
    for the iOS / manual instructions.
--}}
@props(['variant' => 'shell'])

@if ($variant === 'chip')
    <button
        type="button"
        wire:ignore
        x-show="$store.pwaInstall.visible"
        style="display: none;"
        data-test="pwa-install-chip"
        @click="$store.pwaInstall.activate()"
        :aria-haspopup="$store.pwaInstall.mode === 'prompt' ? null : 'dialog'"
        {{ $attributes->class('nt-btn nt-btn-ghost nt-btn-sm shrink-0') }}
    >
        <x-site.icon name="down" class="h-4 w-4" />
        <span>Install app</span>
    </button>
@else
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
@endif
