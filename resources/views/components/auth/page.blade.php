{{-- Sign-in and gate pages (Fortify's views, consent, choose a name): the site shell's centred panel. --}}
@props(['title' => null])
@include('layouts.site', [
    'shell' => 'panel',
    'noindex' => true,
    'page' => 'auth',
    'title' => $title,
    'description' => 'Sign in or create your Kadi account to play.',
])
