<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Night table · UI gallery</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body { background: radial-gradient(1200px 520px at 70% -120px, rgb(248 192 56 / .07), transparent 70%), var(--color-night); }
        .swatch { height: 64px; border-radius: 12px; border: 1px solid var(--color-line); }
    </style>
</head>
<body class="min-h-screen bg-night font-sans text-ivory antialiased">
<main class="mx-auto max-w-6xl space-y-10 px-4 py-10 sm:px-8" data-ui-gallery>
    <x-ui.page-head title="Night table" crumb="Kadi · design system" description="Every component in every state. Local only: this page answers nowhere else.">
        <x-slot:actions><x-ui.button variant="ghost" href="{{ route('home') }}">Back to the site</x-ui.button></x-slot:actions>
    </x-ui.page-head>

    {{-- Tokens --}}
    <x-ui.panel padded aria-labelledby="t-tokens">
        <x-ui.section-head id="t-tokens" title="Colour" sub="Gold is the only accent" />
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
            @foreach (['night', 'felt', 'felt-2', 'felt-3', 'felt-4', 'gold', 'gold-2', 'gold-dim', 'navy', 'ivory', 'soft', 'ash', 'win', 'hot'] as $token)
                <div><div class="swatch" style="background: var(--color-{{ $token }})"></div><p class="mt-1 text-xs text-ash">{{ $token }}</p></div>
            @endforeach
        </div>
        <div class="mt-6 space-y-1">
            <p class="text-3xl font-extrabold">Kadi tables are open</p>
            <p class="text-xl font-bold">Section heading, Outfit 700</p>
            <p class="text-soft">Interface text, Outfit 400. Prices and money use tabular figures: <span class="nt-num font-bold text-gold">KES 1,250.00</span></p>
            <p class="font-card text-2xl font-extrabold text-ivory">Cinzel lives only on the cards</p>
        </div>
    </x-ui.panel>

    {{-- Buttons --}}
    <x-ui.panel padded aria-labelledby="t-buttons">
        <x-ui.section-head id="t-buttons" title="Buttons" />
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button>Play Kadi</x-ui.button>
            <x-ui.button variant="ghost">How to play</x-ui.button>
            <x-ui.button variant="quiet">Clear all</x-ui.button>
            <x-ui.button size="sm">Buy</x-ui.button>
            <x-ui.button size="lg">Place bet KES 100</x-ui.button>
            <x-ui.button disabled>Checking your bet…</x-ui.button>
            <x-ui.icon-button label="Notifications, 1 new" dot><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/></svg></x-ui.icon-button>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-ui.pill>Neutral</x-ui.pill><x-ui.pill tone="ok">E-mail verified</x-ui.pill><x-ui.pill tone="warn">Phone not confirmed</x-ui.pill><x-ui.pill tone="hot">Suspended</x-ui.pill><x-ui.pill tone="gold">Jackpot</x-ui.pill>
        </div>
    </x-ui.panel>

    {{-- Tabs and chips --}}
    <x-ui.panel padded aria-labelledby="t-tabs" x-data="{ tab: 'highlights', theme: null }">
        <x-ui.section-head id="t-tabs" title="Tabs and chips" />
        <x-ui.tabs label="When">
            @foreach (['highlights' => 'Highlights', 'today' => 'Today', 'tomorrow' => 'Tomorrow', 'later' => 'Later this week'] as $key => $label)
                <x-ui.tab :active="$key === 'highlights'" x-on:click="tab = '{{ $key }}'" x-bind:aria-selected="tab === '{{ $key }}'">{{ $label }}</x-ui.tab>
            @endforeach
        </x-ui.tabs>
        <x-ui.tabs label="Account" as="nav" class="mt-3">
            <x-ui.tab href="#" active>Wallet</x-ui.tab><x-ui.tab href="#">Activity</x-ui.tab><x-ui.tab href="#">Invite &amp; Earn</x-ui.tab>
        </x-ui.tabs>
        <x-ui.chips label="Slot themes" class="mt-4">
            <x-ui.chip pressed>Featured</x-ui.chip>
            @foreach (config('games.collections') as $collection)
                <x-ui.chip>{{ $collection['label'] }}</x-ui.chip>
            @endforeach
        </x-ui.chips>
    </x-ui.panel>

    {{-- Odds --}}
    <x-ui.panel padded aria-labelledby="t-odds">
        <x-ui.section-head id="t-odds" title="Odds buttons" sub="Selected, moved and suspended" />
        <div class="grid grid-cols-3 gap-2 sm:max-w-xl">
            <x-ui.odds-button label="1" price="2.61" pick="Brentford to win" />
            <x-ui.odds-button label="X" price="3.56" pick="Draw" selected />
            <x-ui.odds-button label="2" price="2.52" pick="Liverpool to win" movement="up" />
            <x-ui.odds-button label="Over" price="1.84" pick="Over 2.5 goals" movement="down" />
            <x-ui.odds-button label="Under" price="1.96" pick="Under 2.5 goals" movement="down" selected />
            <x-ui.odds-button label="BTTS" pick="Both teams score" suspended />
        </div>
        <div class="mt-5"><x-ui.slip-pill :count="3" payout="KES 1,684" /></div>
    </x-ui.panel>

    {{-- Tiles --}}
    <x-ui.panel padded aria-labelledby="t-tiles">
        <x-ui.section-head id="t-tiles" title="Game tiles" sub="Hover or focus a tile; the last one has no artwork" />
        <div class="nt-tile-row">
            @foreach ($titles as $i => $title)
                <x-ui.game-tile :title="$title" href="#" :favourite="$i === 1 ? true : ($title->presented ? false : null)" />
            @endforeach
        </div>
    </x-ui.panel>

    {{-- Rows, stats, notice, promo --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.panel aria-labelledby="t-rows">
            <div class="px-5 pt-5 sm:px-[22px]"><x-ui.section-head id="t-rows" title="Rows" /></div>
            <x-ui.row title="Deposit · M-Pesa" meta="Today 14:02 · ref SKL4H2X9">
                <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4v12M7 11l5 5 5-5M5 20h14"/></svg></x-slot:icon>
                <x-slot:trail><span class="nt-in">+ 1,000.00</span></x-slot:trail>
            </x-ui.row>
            <x-ui.row title="Kadi single game v Mwangi_K" meta="Today 13:40 · lost · stake KES 50">
                <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="11" height="15" rx="2" transform="rotate(-10 9 12.5)"/><rect x="9.5" y="4" width="11" height="15" rx="2"/></svg></x-slot:icon>
                <x-slot:trail><x-ui.button variant="ghost" size="sm">Report</x-ui.button></x-slot:trail>
            </x-ui.row>
        </x-ui.panel>
        <div class="space-y-4">
            <x-ui.panel padded class="grid grid-cols-3 gap-4">
                <x-ui.stat label="joined" value="7" /><x-ui.stat label="confirmed" value="4" /><x-ui.stat label="earned" value="KES 200" gold />
            </x-ui.panel>
            <x-ui.promo-card title="KES 20 welcome bonus" text="Unlocks when your phone is confirmed" :image="\App\Games\GameArtwork::thumbnailUrl('royal_gems')" />
        </div>
    </div>
    <div class="overflow-hidden rounded-panel border border-line">
        <x-ui.notice><b>Confirm your phone number</b> to deposit, withdraw and unlock your KES 20 welcome bonus.
            <x-slot:action><x-ui.button size="sm">Confirm phone</x-ui.button></x-slot:action>
        </x-ui.notice>
    </div>

    {{-- The landing hero (components/lobby/hero, resources/js/site/hero-slider.js), from 768px wide --}}
    <section aria-labelledby="t-hero" class="space-y-3">
        <x-ui.section-head id="t-hero" title="Landing hero" sub="Lead slide, an object layer (iris) and a background (parallax); 768px and wider" />
        <x-lobby.hero :slides="$heroSlides" />
    </section>

    {{-- The Kadi hand and sheets --}}
    <x-ui.panel padded class="nt-hand-host nt-reveal overflow-hidden" aria-labelledby="t-hand">
        <x-ui.section-head id="t-hand" title="The Kadi hand" sub="Dealt in on load, fans out on hover" />
        <div class="flex flex-wrap items-center gap-8">
            <x-ui.kadi-hand />
            <div class="space-y-3">
                <x-ui.button x-data x-on:click="$dispatch('open-sheet', 'demo-drawer')">Open a drawer</x-ui.button>
                <x-ui.button variant="ghost" x-data x-on:click="$dispatch('open-sheet', 'demo-dialog')">Open a dialog</x-ui.button>
            </div>
        </div>
    </x-ui.panel>
</main>

<x-ui.sheet name="demo-drawer" title="Welcome back">
    <p class="mb-4 text-sm text-ash">A drawer from the right on desktop; a bottom sheet on phones.</p>
    <x-ui.button block>Log in</x-ui.button>
</x-ui.sheet>
<x-ui.sheet name="demo-dialog" title="Deposit with M-Pesa" center>
    <p class="mb-4 text-sm text-ash">A centred dialog on desktop; still a bottom sheet on phones.</p>
    <x-ui.button block>Send request to my phone</x-ui.button>
</x-ui.sheet>
@livewireScripts
</body>
</html>
