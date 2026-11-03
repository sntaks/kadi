<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="h-screen overflow-hidden bg-[#0a0a0a] antialiased" style="font-family: 'Outfit', sans-serif;">

        <div
            class="flex h-screen overflow-hidden"
            x-data="{
                sidebarOpen: false,
                expanded: JSON.parse(localStorage.getItem('sidebar_expanded') ?? 'false'),
                toggle() {
                    this.expanded = !this.expanded;
                    localStorage.setItem('sidebar_expanded', JSON.stringify(this.expanded));
                },
                showLabels() {
                    return this.expanded || window.innerWidth < 1024;
                }
            }"
            @bottom-nav:toggle-menu.window='sidebarOpen = true'
        >

            {{-- Sidebar overlay (mobile) --}}
            <div
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-linear duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="sidebarOpen = false"
                class="fixed inset-0 z-20 bg-black/60 lg:hidden"
                x-cloak
            ></div>

            {{-- Sidebar --}}
            <aside
                x-cloak
                :class="{
                    'translate-x-0': sidebarOpen,
                    '-translate-x-full': !sidebarOpen,
                    'w-64': true,
                    'lg:w-56': expanded,
                    'lg:w-16': !expanded
                }"
                class="fixed inset-y-0 left-0 z-30 w-64 flex flex-col border-r border-yellow-800/30 bg-[#111111] transition-all duration-300 ease-in-out lg:static lg:translate-x-0 lg:overflow-y-auto flex-shrink-0"
            >
                {{-- Logo --}}
                <div class="flex h-16 items-center border-b border-yellow-800/20 overflow-hidden flex-shrink-0"
                     :class="showLabels() ? 'px-6 justify-start' : 'px-0 justify-center'">
                    <a href="{{ route('home') }}"
                       class="text-lg tracking-widest text-[#f5c542] flex-shrink-0"
                       style="font-family: 'Cinzel', serif;"
                       wire:navigate>
                        <span
                            x-show="showLabels()"
                            x-transition:enter="transition-opacity duration-200"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                            class="whitespace-nowrap">♠ {{ strtoupper(config('app.name')) }}</span>
                        <span x-show="!expanded">♠</span>
                    </a>
                </div>

                {{-- Navigation --}}
                <nav class="flex flex-1 flex-col gap-1 p-2 overflow-y-auto">

                    {{-- ================= MAIN ================= --}}
                    <div
                        x-show="showLabels()"
                        class="px-3 pt-2 pb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500"
                    >
                        Main
                    </div>
                    {{-- Dashboard --}}
                    <a href="{{ route('dashboard') }}" wire:navigate
                       :class="showLabels() ? 'justify-start' : 'justify-center px-0'"
                       :title="!expanded ? 'Dashboard' : ''"
                       @class([
                           'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all',
                           'bg-[#f5c542]/10 text-[#f5c542] border-l-2 border-[#f5c542]'                             => request()->routeIs('dashboard'),
                           'text-gray-400 hover:text-white hover:bg-[#161616] border-l-2 border-transparent'        => !request()->routeIs('dashboard'),
                       ])>
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span
                            x-show="showLabels()"
                            x-transition:enter="transition-opacity duration-200"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                            class="text-sm font-medium whitespace-nowrap">Dashboard</span>
                    </a>

                    {{-- Divider --}}
                    <div class="my-4 border-t border-yellow-800/20"></div>
                    {{-- ================= WALLET ================= --}}
                    <div
                        x-show="showLabels()"
                        class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500"
                    >
                        Wallet
                    </div>
                    {{-- Vault --}}
                    <a href="{{ route('wallet') }}" wire:navigate
                       :class="showLabels() ? 'justify-start' : 'justify-center px-0'"
                       :title="!expanded ? 'Vault' : ''"
                       @class([
                           'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all',
                           'bg-[#f5c542]/10 text-[#f5c542] border-l-2 border-[#f5c542]'                             => request()->routeIs('wallet'),
                           'text-gray-400 hover:text-white hover:bg-[#161616] border-l-2 border-transparent'        => !request()->routeIs('wallet'),
                       ])>
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6 9a6 6 0 1112 0v5a4 4 0 01-4 4H9a4 4 0 01-4-4V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M10 6h4"/>
                            <circle cx="12" cy="12" r="2.5"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 10.5v3M10.5 12H13.5"/>
                        </svg>
                        <span x-show="showLabels()" x-transition:enter="transition-opacity duration-200"
                              x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                              class="text-sm font-medium whitespace-nowrap">Vault</span>
                    </a>
                    {{-- Game History --}}
                    <a href="{{ route('games.history') }}" wire:navigate
                       :class="showLabels() ? 'justify-start' : 'justify-center px-0'"
                       :title="!expanded ? 'Game History' : ''"
                       @class([
                           'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all',
                           'bg-[#f5c542]/10 text-[#f5c542] border-l-2 border-[#f5c542]'                             => request()->routeIs('games.history'),
                           'text-gray-400 hover:text-white hover:bg-[#161616] border-l-2 border-transparent'        => !request()->routeIs('games.history'),
                       ])>
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-show="showLabels()" x-transition:enter="transition-opacity duration-200"
                              x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                              class="text-sm font-medium whitespace-nowrap">Game History</span>
                    </a>
                    @if (config('kadi.referrals.enabled'))
                    {{-- Invite & Earn --}}
                    <a href="{{ route('referrals') }}" wire:navigate
                       :class="showLabels() ? 'justify-start' : 'justify-center px-0'"
                       :title="!expanded ? 'Invite & Earn' : ''"
                       @class([
                           'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all',
                           'bg-[#f5c542]/10 text-[#f5c542] border-l-2 border-[#f5c542]'                             => request()->routeIs('referrals'),
                           'text-gray-400 hover:text-white hover:bg-[#161616] border-l-2 border-transparent'        => !request()->routeIs('referrals'),
                       ])>
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
                        </svg>
                        <span x-show="showLabels()" x-transition:enter="transition-opacity duration-200"
                              x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                              class="text-sm font-medium whitespace-nowrap">Invite &amp; Earn</span>
                    </a>
                    @endif
                    {{-- Divider --}}
                    <div class="my-4 border-t border-yellow-800/20"></div>
                    {{-- ================= ACCOUNT ================= --}}
                    <div
                        x-show="showLabels()"
                        class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500"
                    >
                        Account
                    </div>
                    {{-- Profile --}}
                    <a href="{{ route('profile') }}" wire:navigate
                       :class="showLabels() ? 'justify-start' : 'justify-center px-0'"
                       :title="!expanded ? 'Profile' : ''"
                        @class([
                            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all',
                            'bg-[#f5c542]/10 text-[#f5c542] border-l-2 border-[#f5c542]'                             => request()->routeIs('profile'),
                            'text-gray-400 hover:text-white hover:bg-[#161616] border-l-2 border-transparent'        => !request()->routeIs('profile'),
                        ])>
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span
                            x-show="showLabels()"
                            x-transition:enter="transition-opacity duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            class="text-sm font-medium whitespace-nowrap">Profile</span>
                    </a>

                    {{-- Install app (only shows where the browser can install; see resources/js/pwa/install.js) --}}
                    <x-pwa.install-button variant="nav" />

                    {{-- Logout --}}
                    <div class="mt-auto pt-4 border-t border-yellow-800/20">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    :class="showLabels() ? 'justify-start' : 'justify-center px-0'"
                                    :title="!expanded ? 'Logout' : ''"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-400 transition hover:bg-red-500/5 hover:text-red-400 border-l-2 border-transparent">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span x-show="showLabels()" x-transition:enter="transition-opacity duration-200"
                                      x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                      class="text-sm font-medium whitespace-nowrap">Logout</span>
                            </button>
                        </form>
                    </div>

                </nav>
            </aside>

            {{-- Main content --}}
            <div class="flex flex-1 flex-col min-w-0 overflow-hidden">

                {{-- Top bar --}}
                <header class="flex h-16 flex-shrink-0 items-center justify-between border-b border-yellow-800/20 bg-[#111111] px-4 sm:px-6">
                    {{-- Hamburger — mobile: toggles overlay, desktop: toggles expand/collapse --}}
                    <button @click="window.innerWidth >= 1024 ? toggle() : (sidebarOpen = !sidebarOpen)" class="text-[#f5f5f0]/60 hover:text-[#f5c542] transition">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div class="hidden text-sm text-[#f5f5f0]/60 lg:block">
                        @include('partials.greeting', ['slot' => ', '])
                        <span class='text-[#f5f5f0]'>{{ strtok(auth()->user()->name, ' ') }}</span>
                    </div>

                    <div class="flex min-w-0 items-center gap-2 sm:gap-4">
                        {{-- Balance widget --}}
                        <livewire:wallet-balance />
                        {{-- Notifications bell --}}
                        <livewire:notifications-bell wire:key="notifications-bell-app" />
                        {{-- Logout --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            {{-- Icon only on the narrowest phones, so the header fits at 320px --}}
                            <button type="submit" aria-label="Logout" class="btn-casino-primary inline-flex items-center rounded-full px-3 py-2 text-sm min-[400px]:px-4 sm:px-5 no-underline">
                                <flux:icon.arrow-right-start-on-rectangle variant="mini" class="min-[400px]:hidden" aria-hidden="true" />
                                <span class="hidden min-[400px]:inline">Logout</span>
                            </button>
                        </form>
                    </div>
                </header>

                {{-- Page content --}}
                <main id="app-scroll-container" class="flex-1 overflow-auto bg-[#0a0a0a] p-6 pb-24 lg:pb-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-structured-data :page="$page ?? 'dashboard'" :noindex="true" />
        @auth
            <livewire:phone-required />
            <x-pwa.install-dialog />
        @endauth
        @fluxScripts
        <livewire:navigation.bottom-nav />
    </body>
</html>
