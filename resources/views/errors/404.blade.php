@php
    $title = 'Page Not Found | Kadi';
    $description = 'The page you were looking for doesn\'t exist. Head back to Kadi and keep playing.';
    $noindex = true;
    $page = '404';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-night antialiased">
        <nav class="border-b border-gold/10 px-6 py-4">
            <a href="{{ route('home') }}" class="text-xl tracking-widest text-gold">
                ♠ {{ strtoupper(config('app.name')) }}
            </a>
        </nav>

        <main class="flex min-h-[70vh] flex-col items-center justify-center px-6 py-24 text-center">
            <div class="text-sm font-semibold uppercase tracking-[0.3em] text-gold/70">
                Error 404
            </div>
            <h1 class="mt-4 text-4xl font-bold text-ivory md:text-5xl">
                This Hand's Not in Play
            </h1>
            <p class="mt-4 max-w-md text-base text-ash">
                The page you're looking for doesn't exist or may have moved. Let's get you back to the table.
            </p>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('home') }}" wire:navigate
                   class="nt-btn nt-btn-gold nt-btn-lg">
                    Back to Home
                </a>
                <a href="{{ route('rules') }}" wire:navigate
                   class="nt-btn nt-btn-ghost nt-btn-lg">
                    Read the Rules
                </a>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-6 text-sm text-ash">
                <a href="{{ route('game-guide') }}" class="transition hover:text-gold">Games & Prizes</a>
                <a href="{{ route('legal.terms') }}" class="transition hover:text-gold">Terms</a>
                <a href="{{ route('legal.privacy') }}" class="transition hover:text-gold">Privacy</a>
            </div>
        </main>
    </body>
</html>
