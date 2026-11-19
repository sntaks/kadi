{{-- One lobby list's "Showing X of Y" and its own "Load more" (action: loadMoreKadi / loadMoreWorld). --}}
@props(['list', 'groups', 'action'])

@php($shown = collect($groups)->sum(fn ($g) => count($g['markets'])))
<div class="nt-pm-more-bar">
    <span class="nt-num" aria-live="polite">Showing {{ number_format($shown) }} of {{ number_format($list['total']) }}</span>
    @if ($list['more'])
        <x-ui.button variant="ghost" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}" data-predict-load-more="{{ $action }}">
            <span wire:loading.remove wire:target="{{ $action }}">Load more markets</span>
            <span wire:loading wire:target="{{ $action }}">Loading…</span>
        </x-ui.button>
    @elseif ($shown < $list['total'])
        <span class="nt-pm-note">Pick a category to see the rest.</span>
    @endif
</div>
