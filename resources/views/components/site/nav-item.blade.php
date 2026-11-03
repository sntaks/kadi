{{--
    One menu link from App\Support\SiteNavigation, rendered the same way in the top bar, side menu,
    footer and bottom bar. "Play Kadi" goes through <x-play-kadi> (link, launcher form or ggid form);
    everything else is a link. A link that needs an account skips wire:navigate (the `auth` redirect
    must run) and carries data-needs-account for the sign-in sheet (Phase 6b). The active row brightens.
--}}
@props(['item', 'class' => 'nt-nav-item', 'icon' => true, 'label' => null])
@php($label ??= $item['label'])
@if ($item['kind'] === 'play-kadi')
    <x-play-kadi :class="$class" :aria-current="$item['active'] ? 'page' : null">
        @if ($icon)<x-site.icon :name="$item['icon']" />@endif
        <span>{{ $label }}</span>
    </x-play-kadi>
@else
    <a href="{{ $item['href'] }}" @if ($class) class="{{ $class }}" @endif
       @if (! $item['external'] && ! $item['needsAccount']) wire:navigate @endif
       @if ($item['active']) aria-current="page" @endif
       @if ($item['needsAccount']) data-needs-account @endif
       @if ($item['external']) target="_blank" rel="noopener noreferrer" @endif>
        @if ($icon)<x-site.icon :name="$item['icon']" />@endif
        <span>{{ $label }}</span>
        @if ($item['external'])
            @if ($icon)<x-site.icon name="external" class="nt-nav-end" />@endif
            <span class="sr-only">(opens in a new tab)</span>
        @endif
    </a>
@endif
