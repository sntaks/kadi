{{-- /activity's game filter: one clear dropdown (styled like the sports league picker) instead of a row of pills.
     A <details> menu, so it opens without JavaScript and each game is a real ?game= link; with Livewire the link
     switches the list in place. wire:ignore.self keeps it open while the list re-renders behind it. --}}
@props(['tabs', 'current', 'icons' => []])
<div {{ $attributes->class('nt-gs') }}>
    <details class="nt-gs-menu" wire:ignore.self x-data
             x-on:click.outside="$el.open = false"
             x-on:keydown.escape="if ($el.open) { $el.open = false; $el.querySelector('summary').focus(); $event.stopPropagation() }">
        <summary class="nt-lp-trigger nt-gs-trigger" aria-describedby="activity-game-hint" data-test="activity-game-select">
            <x-site.icon :name="$icons[$current] ?? 'chip'" />
            <span class="nt-lp-two"><em>Showing</em><b>{{ $tabs[$current] ?? 'Kadi' }}</b></span>
            <x-site.icon name="chev" />
            <span class="sr-only">Choose a game</span>
        </summary>
        <div class="nt-gs-list" role="list" aria-label="Games">
            @foreach ($tabs as $key => $label)
                <a role="listitem" class="nt-lp-row" href="{{ route('activity', ['game' => $key]) }}"
                   wire:click.prevent="show('{{ $key }}')" x-on:click="$el.closest('details').open = false"
                   @if ($key === $current) aria-current="page" @endif data-test="activity-tab-{{ $key }}">
                    <x-site.icon :name="$icons[$key] ?? 'chip'" /><span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </details>
    <p class="nt-gs-hint" id="activity-game-hint">Pick a game to see your plays and how they ended.</p>
</div>
