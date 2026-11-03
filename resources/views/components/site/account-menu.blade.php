{{--
    The avatar menu (top right on desktop). A disclosure with plain links, so Tab and Enter work as
    everywhere else; Esc and a click outside close it and focus returns to the avatar.
--}}
@props(['nav'])
@php
    $user = auth()->user();
    $initials = $user->initials();
@endphp
<div class="nt-acct relative" x-data="{ open: false }"
     x-on:keydown.escape="if (open) { open = false; $refs.avatar.focus() }"
     x-on:click.outside="open = false">
    <button type="button" class="nt-avatar" x-ref="avatar" aria-label="Your account" aria-controls="site-account-menu"
            x-bind:aria-expanded="open" x-on:click="open = ! open">{{ $initials ?: '♠' }}</button>

    <div id="site-account-menu" class="nt-menu-pop" x-show="open" x-cloak
         x-transition:enter="nt-sheet-anim" x-transition:enter-start="opacity-0" x-transition:leave="nt-sheet-anim" x-transition:leave-end="opacity-0">
        <div class="nt-who">
            <span class="nt-avatar" aria-hidden="true">{{ $initials ?: '♠' }}</span>
            <div><b>{{ $user->name }}</b><span>{{ $user->email }}</span></div>
        </div>
        @unless ($nav->isGated())
            @foreach ($nav->accountMenu() as $section)
                @foreach ($section as $item)
                    <a href="{{ $item['href'] }}" class="nt-mi" wire:navigate x-on:click="open = false">
                        <x-site.icon :name="$item['icon']" />{{ $item['label'] }}
                    </a>
                @endforeach
                <hr>
            @endforeach
            <x-pwa.install-button variant="shell" />
        @endunless
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nt-mi"><x-site.icon name="out" />Log out</button>
        </form>
    </div>
</div>
