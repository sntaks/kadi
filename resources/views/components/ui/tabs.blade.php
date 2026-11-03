{{--
    Pill tabs. As navigation (links, `nav`): each <x-ui.tab> gets an href and `active` sets aria-current.
    As in-page tabs (`tablist`): tabs are buttons with role="tab"; `active` sets aria-selected. Arrow keys,
    Home and End move between tabs and select them (the roving tabindex follows the selection).
--}}
@props(['label', 'as' => 'tablist'])
@if ($as === 'nav')
    <nav aria-label="{{ $label }}" {{ $attributes->class('nt-tabs') }}>{{ $slot }}</nav>
@else
    <div role="tablist" aria-label="{{ $label }}"
         x-data="{
             tabs() { return [...$el.querySelectorAll('[role=tab]')].filter((t) => !t.disabled) },
             select(tab) { this.tabs().forEach((t) => t.tabIndex = t === tab ? 0 : -1) },
             move(step) {
                 const tabs = this.tabs(), i = tabs.indexOf(document.activeElement);
                 const next = step === 'first' ? tabs[0] : step === 'last' ? tabs[tabs.length - 1] : tabs[(i + step + tabs.length) % tabs.length];
                 next?.focus(); next?.click();
             },
         }"
         x-on:click="$event.target.closest('[role=tab]') && select($event.target.closest('[role=tab]'))"
         x-on:keydown.right.prevent="move(1)" x-on:keydown.left.prevent="move(-1)"
         x-on:keydown.home.prevent="move('first')" x-on:keydown.end.prevent="move('last')"
         {{ $attributes->class('nt-tabs') }}>{{ $slot }}</div>
@endif
