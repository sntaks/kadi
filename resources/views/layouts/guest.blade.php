<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#0a0a0a] antialiased" style="font-family: 'Outfit', sans-serif;">
        @include('partials.preloader')

        {{-- Navbar --}}
        <nav
            x-data="{ scrolled: false, menuOpen: false }"
            @bottom-nav:toggle-menu.window="menuOpen = true"
            x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 60 })"
            :class="scrolled || menuOpen ? 'bg-black/80 backdrop-blur-xl border-b border-[#f5c542]/10 shadow-lg' : 'bg-transparent border-b border-transparent'"
            class="fixed top-0 left-0 right-0 z-50 transition-all duration-500 ease-in-out"
        >
            {{-- Top bar --}}
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                {{-- Logo --}}
                <a href="{{ route('home') }}" class="text-xl tracking-widest text-[#f5c542]" style="font-family: 'Cinzel', serif;" wire:navigate>
                    ♠ {{ strtoupper(config('app.name')) }}
                </a>

                {{-- Desktop nav links --}}
                <div class="hidden items-center gap-8 md:flex">
                    <a href="{{ route('home') }}" class="text-sm text-[#f5f5f0]/70 transition hover:text-[#f5c542]" wire:navigate>Home</a>
                    <a href="{{ route('rules') }}" wire:navigate class="text-sm transition {{ request()->routeIs('rules') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Rules</a>
                    <a href="{{ route('game-guide') }}" wire:navigate class="text-sm transition {{ request()->routeIs('game-guide') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Games & Prizes</a>
                    <a href="{{ route('faq') }}" wire:navigate class="text-sm transition {{ request()->routeIs('faq') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">FAQ</a>
                    {{--
                    <a href="{{ route('guest.games') }}" class="text-sm text-[#f5f5f0]/70 transition hover:text-[#f5c542]" wire:navigate>Casino</a>
                    <a href="{{ route('sportsbook') }}" wire:navigate class="text-sm transition {{ request()->routeIs('sportsbook') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Sports</a>
                    --}}
                    @auth
{{--                        <a href="{{ route('buy-coins') }}" wire:navigate class="text-sm transition {{ request()->routeIs('buy-coins') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Buy Coins</a>--}}
{{--                        <a href="{{ route('earn-coins') }}" wire:navigate class="text-sm transition {{ request()->routeIs('earn-coins') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Free Coins</a>--}}
{{--                        <a href="{{ url('/marketing/ad-campaigns') }}" wire:navigate class="text-sm transition {{ request()->routeIs('earn-coins') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Campaigns</a>--}}
                        <a href="{{ route('profile') }}" wire:navigate class="text-sm transition {{ request()->routeIs('earn-coins') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Profile</a>
                    @endauth
                    {{--
                     <a href="#about" class="text-sm text-[#f5f5f0]/70 transition hover:text-[#f5c542]">About</a>
                    <a href="#promotions" class="text-sm text-[#f5f5f0]/70 transition hover:text-[#f5c542]">Promotion</a>
                     --}}
                </div>

                {{-- Right side: auth button + hamburger --}}
                <div class="flex items-center gap-3">
                    <div class="hidden md:flex items-center gap-3">
                        @auth
                            {{-- Install pill: only rendered visible when the browser can install (see resources/js/pwa/install.js) --}}
                            <x-pwa.install-button variant="chip" />
                            <livewire:wallet-balance wire:key="wallet-balance-desktop" />
                            <livewire:notifications-bell wire:key="notifications-bell-desktop" />
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex items-center gap-1.5 text-sm font-semibold text-[#f5c542] transition hover:text-[#ffde74] cursor-pointer bg-transparent border-0 p-0">
                                    <span>Logout</span>
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" wire:navigate
                               class="btn-casino-ghost inline-block rounded-full px-5 py-2 text-sm no-underline">
                                Login
                            </a>
                        @endauth
                    </div>

                    {{-- Wallet + notifications (mobile only) --}}
                    @auth
                        <div class="flex items-center gap-2 md:hidden">
                            <livewire:wallet-balance wire:key="wallet-balance-mobile" />
                            <livewire:notifications-bell wire:key="notifications-bell-mobile" />
                        </div>
                    @endauth

                    {{-- Hamburger (mobile only) --}}
                    <button
                        @click="menuOpen = !menuOpen"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#f5c542]/20 text-[#f5f5f0]/70 transition hover:border-[#f5c542]/50 hover:text-[#f5c542] md:hidden"
                        aria-label="Toggle menu"
                    >
                        <svg x-show="!menuOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="menuOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" x-cloak>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mobile dropdown menu --}}
            <div
                x-show="menuOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                @click.away="menuOpen = false"
                class="border-t border-[#f5c542]/10 bg-black/90 backdrop-blur-xl md:hidden"
                x-cloak
            >
                <div class="flex flex-col divide-y divide-[#f5c542]/10 px-6 py-2">
                    <a href="{{ route('home') }}" @click="menuOpen = false" wire:navigate
                       class="py-3 text-sm text-[#f5f5f0]/70 transition hover:text-[#f5c542]">Home</a>
                    <a href="{{ route('rules') }}" @click="menuOpen = false" wire:navigate
                       class="py-3 text-sm transition {{ request()->routeIs('rules') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Rules</a>
                    <a href="{{ route('game-guide') }}" @click="menuOpen = false" wire:navigate
                       class="py-3 text-sm transition {{ request()->routeIs('game-guide') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Games & Prizes</a>
                    <a href="{{ route('faq') }}" @click="menuOpen = false" wire:navigate
                       class="py-3 text-sm transition {{ request()->routeIs('faq') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">FAQ</a>
                    {{--
                    <a href="{{ route('guest.games') }}" @click="menuOpen = false" wire:navigate
                       class="py-3 text-sm text-[#f5f5f0]/70 transition hover:text-[#f5c542]">Casino</a>
                    <a href="{{ route('sportsbook') }}" @click="menuOpen = false" wire:navigate
                       class="py-3 text-sm transition {{ request()->routeIs('sportsbook') ? 'text-[#f5c542] font-bold' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">Sports</a>
                    --}}

                    @auth
                        {{-- Phones: the header is already full, so the install entry lives in this menu. --}}
                        <x-pwa.install-button variant="menu" />

                        <div class="py-4">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button
                                    type="submit"
                                    @click="menuOpen = false"
                                    class="flex items-center gap-2 py-3 text-sm font-semibold text-[#f5c542] transition hover:text-[#ffde74] cursor-pointer bg-transparent border-0 p-0 w-full">
                                </button>
                            </form>
                        </div>
                        @else
                            <div class="flex flex-col gap-3">
                                <a href="{{ route('login') }}" @click="menuOpen = false" wire:navigate
                                   class="btn-casino-primary block w-full rounded-xl py-3 text-center text-sm font-semibold no-underline">
                                    Enter the Arena 🎰
                                </a>
                                <a href="{{ route('register') }}" @click="menuOpen = false" wire:navigate
                                   class="btn-casino-ghost block w-full rounded-xl py-3 text-center text-sm font-semibold no-underline">
                                    Create Account →
                                </a>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        {{-- Page content --}}
        {{ $slot }}

        {{-- Footer --}}
        <footer class="border-t border-[#f5c542]/30 bg-[#0a0a0a]">
            <div class="mx-auto max-w-7xl px-6 py-14 md:py-16">
                <div class="grid grid-cols-1 gap-10 md:grid-cols-12 md:gap-8">
                    {{-- Brand + socials --}}
                    <div class="md:col-span-5 lg:col-span-4">
                        <div class="mb-4 text-xl tracking-widest text-[#f5c542]" style="font-family: 'Cinzel', serif;">♠ {{ strtoupper(config('app.name')) }}</div>
                        <p class="max-w-sm text-sm leading-relaxed text-zinc-400">
                            The home of competitive Kadi in Kenya. Play against real opponents, sharpen your strategy, and experience the excitement of online Kadi esports.
                        </p>

                        @if ($socials = \App\Support\Contact::socials())
                            <h4 class="mt-7 mb-3 text-xs font-semibold uppercase tracking-widest text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Follow Kadi</h4>
                            <ul class="flex flex-wrap gap-3">
                                @foreach ($socials as $social)
                                    <li>
                                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                                           aria-label="Kadi on {{ $social['label'] }} (opens in a new tab)" title="{{ $social['label'] }}"
                                           class="flex size-11 items-center justify-center rounded-full border border-[#f5c542]/15 bg-white/[0.04] transition hover:-translate-y-0.5 hover:border-[#f5c542]/50 hover:bg-white/[0.08] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5c542]">
                                            <img src="{{ asset($social['icon']) }}" alt="" width="20" height="20" loading="lazy" class="size-5 object-contain">
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Quick Links --}}
                    <div class="md:col-span-3 lg:col-span-3 lg:col-start-6">
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-widest text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Quick Links</h4>
                        <ul class="space-y-0.5">
                            @foreach ([
                                ['route' => 'rules', 'label' => 'Rules & Card Guide'],
                                ['route' => 'game-guide', 'label' => 'Games & Prizes'],
                                ['route' => 'faq', 'label' => 'FAQ'],
                                ['route' => 'legal.terms', 'label' => 'Terms & Conditions'],
                                ['route' => 'legal.privacy', 'label' => 'Privacy Policy'],
                            ] as $link)
                                <li>
                                    <a href="{{ route($link['route']) }}" wire:navigate
                                       class="inline-block py-1.5 text-sm transition {{ request()->routeIs($link['route']) ? 'text-[#f5c542]' : 'text-zinc-400 hover:text-[#f5c542]' }}"
                                       @if (request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Get help: every entry is one tap (call, chat, email). --}}
                    <div class="md:col-span-4 lg:col-span-4 lg:col-start-9">
                        <h4 class="mb-3 text-sm font-semibold uppercase tracking-widest text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Get Help</h4>
                        <ul class="space-y-2.5">
                            @php
                                $help = array_filter([
                                    \App\Support\Contact::phone() ? ['href' => \App\Support\Contact::phoneHref(), 'label' => 'Customer care', 'value' => \App\Support\Contact::phone(), 'icon' => 'call'] : null,
                                    \App\Support\Contact::whatsapp() ? ['href' => \App\Support\Contact::whatsapp(), 'label' => 'WhatsApp', 'value' => 'Chat with us', 'image' => 'socials/whatsapp-icon.svg', 'external' => true] : null,
                                    \App\Support\Contact::email() ? ['href' => 'mailto:'.\App\Support\Contact::email(), 'label' => 'Email', 'value' => \App\Support\Contact::email(), 'icon' => 'mail-01'] : null,
                                ]);
                            @endphp
                            @foreach ($help as $item)
                                <li>
                                    <a href="{{ $item['href'] }}" @if (! empty($item['external'])) target="_blank" rel="noopener noreferrer" @endif
                                       class="group flex items-center gap-3 rounded-xl border border-white/[0.06] bg-white/[0.02] p-3 transition hover:border-[#f5c542]/40 hover:bg-[#f5c542]/[0.04] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5c542]">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-[#f5c542]/20 bg-[#f5c542]/10 text-[#f5c542]">
                                            @if (! empty($item['image']))
                                                <img src="{{ asset($item['image']) }}" alt="" width="20" height="20" loading="lazy" class="size-5">
                                            @else
                                                @svg('hugeicons-'.$item['icon'], 'size-5', ['aria-hidden' => 'true'])
                                            @endif
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[11px] uppercase tracking-wider text-zinc-500">{{ $item['label'] }}</span>
                                            <span class="block truncate text-sm font-medium text-[#f5f5f0] transition group-hover:text-[#f5c542]">{{ $item['value'] }}</span>
                                        </span>
                                        @if (! empty($item['external']))
                                            @svg('hugeicons-arrow-up-right-01', 'size-4 shrink-0 text-zinc-500 transition group-hover:text-[#f5c542]', ['aria-hidden' => 'true'])
                                            <span class="sr-only">(opens in a new tab)</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Extra bottom padding below lg keeps this line clear of the floating bottom nav. --}}
            <div class="border-t border-[#f5c542]/20 bg-black/50 pt-4 pb-28 lg:pb-4">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 text-xs text-zinc-500 sm:flex-row">
                    <p>&copy; {{ date('Y') }} {{ strtoupper(config('app.name')) }}. All rights reserved.</p>
                    <p class="flex items-center gap-2">
                        <span class="rounded-md border border-[#ff6b6b]/40 px-1.5 py-0.5 font-semibold text-[#ff6b6b]">18+</span>
                        <span>Play responsibly</span>
                    </p>
                </div>
            </div>
        </footer>

        <x-structured-data :page="$page ?? 'home'" />
        @auth
            <x-pwa.install-dialog />
        @endauth
        {{--
        @auth
            <livewire:phone-required />
        @endauth
        --}}
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/intersect@3.x.x/dist/cdn.min.js"></script>
        @fluxScripts
        <livewire:navigation.bottom-nav />

    </body>
</html>
