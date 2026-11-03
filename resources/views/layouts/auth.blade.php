{{-- Sign-in and gate forms. A centred panel in the one site shell behind PLATFORM_UI_V2 (Phase 6b turns it into the sign-in sheet). --}}
@if (config('platform.ui_v2'))
    @include('layouts.site', [
        'shell' => 'panel',
        'noindex' => true,
        'page' => 'auth',
        'description' => 'Sign in or create your Kadi account to play.',
    ])
@else
    <x-layouts::auth.simple :title="$title ?? null">
        {{ $slot }}
    </x-layouts::auth.simple>
@endif
