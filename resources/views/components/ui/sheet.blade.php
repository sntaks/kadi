{{--
    A sheet: right-hand drawer on desktop, bottom sheet on phones (`center` for a centred dialog on desktop).
    Open with $dispatch('open-sheet', 'name'), close with $dispatch('close-sheet', 'name'), Esc or the scrim.
    Focus moves in and is trapped while open, then returns to what opened it.
--}}
@props(['name', 'title', 'center' => false])
<div x-data="{ open: false }"
     x-on:open-sheet.window="if ($event.detail === @js($name)) open = true"
     x-on:close-sheet.window="if ($event.detail === @js($name)) open = false"
     x-on:keydown.escape.window="open = false"
     data-sheet="{{ $name }}">
    <div class="nt-scrim" x-show="open" x-cloak x-transition.opacity x-on:click="open = false"></div>
    <div role="dialog" aria-modal="true" aria-labelledby="sheet-{{ $name }}-title"
         {{ $attributes->class(['nt-sheet', 'nt-sheet-center' => $center]) }}
         x-show="open" x-cloak x-trap.inert.noscroll="open"
         {{-- Transform-based classes from night-table.css: they compose with the centring translate. --}}
         x-transition:enter="nt-sheet-anim" x-transition:enter-start="nt-sheet-from" x-transition:enter-end=""
         x-transition:leave="nt-sheet-anim" x-transition:leave-start="" x-transition:leave-end="nt-sheet-from">
        <button type="button" class="nt-icon-btn nt-sheet-close" aria-label="Close" x-on:click="open = false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
        <div class="nt-sheet-inner">
            <h2 id="sheet-{{ $name }}-title">{{ $title }}</h2>
            {{ $slot }}
        </div>
    </div>
</div>
