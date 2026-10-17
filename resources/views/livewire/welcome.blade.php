<div>
    {{-- ===================== EMAIL VERIFICATION BANNER ===================== --}}
    @if ($this->hasUnverifiedEmail)
        <div class="fixed top-16 left-0 right-0 z-40 mx-auto max-w-7xl rounded-b-lg border border-t-0 border-amber-700/40 bg-amber-900/20 px-4 py-3 text-sm text-amber-400">
            {{ __('Please verify your email address. Check your inbox for the verification link.') }}

            <span class="ml-1 cursor-pointer underline hover:text-amber-300" wire:click.prevent="resendVerificationNotification">
                {{ __('Resend verification email') }}
            </span>

            @if (session('status') === 'verification-link-sent')
                <span class="ml-2 font-medium text-green-400">
                    {{ __('A new verification link has been sent to your email address.') }}
                </span>
            @endif
        </div>
    @endif

    {{-- ===================== HERO ===================== --}}
    <section class="relative overflow-hidden bg-[#0a0a0a] min-h-[420px] md:min-h-[480px] flex items-center {{ $this->hasUnverifiedEmail ? 'pt-16' : '' }}">

        {{-- Radial gold glow behind left content --}}
        <div class="absolute top-1/2 left-1/4 -translate-x-1/2 -translate-y-1/2
                    w-[500px] h-[500px] rounded-full pointer-events-none"
             style="background: radial-gradient(circle, rgba(245,197,66,0.07) 0%, transparent 70%);">
        </div>
        {{-- Radial glow behind right content --}}
        <div class="absolute top-1/3 right-1/4 -translate-y-1/2
                    w-[400px] h-[400px] rounded-full pointer-events-none"
             style="background: radial-gradient(circle, rgba(245,197,66,0.05) 0%, transparent 70%);">
        </div>

        {{-- Faded background images (cached) --}}
        @php
            $casinoImages = cache()->remember('welcome_casino_bg_images', 3600, function () {
                return array_slice(glob(public_path('casino/*.{png,jpg,webp}'), GLOB_BRACE), 0, 4);
            });
            $bgPositions = [
                ['top-4 -left-8 md:top-8 md:-left-4', 'rotate-[-15deg]'],
                ['top-0 right-0 md:-right-6',          'rotate-[10deg]'],
                ['bottom-4 left-16 md:bottom-8 md:left-24', 'rotate-[8deg]'],
                ['-bottom-4 right-8 md:right-16',      'rotate-[-12deg]'],
            ];
        @endphp
        @foreach($casinoImages as $i => $imgPath)
            @php [$pos, $rot] = $bgPositions[$i] ?? ['top-0 left-0', '']; @endphp
            <img src="{{ asset('casino/' . basename($imgPath)) }}"
                 alt=""
                 width="208"
                 height="208"
                 class="absolute {{ $pos }} {{ $rot }} opacity-[0.08] pointer-events-none select-none w-40 md:w-52 object-contain"
                 loading="lazy"
                 decoding="async"
                 aria-hidden="true" />
        @endforeach

        {{-- Content grid --}}
        <div class="relative z-10 w-full max-w-6xl mx-auto px-6 md:px-10 py-10 md:py-14">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center">

                {{-- ══ LEFT: Tagline + CTA ══ --}}
                <div class="flex flex-col items-start">

                    {{-- Live badge (hidden when the players API fails and returns 0) --}}
                    @if($livePlayers > 0)
                        <div class="inline-flex items-center gap-2 bg-[#f5c542]/10 border border-[#f5c542]/20 rounded-full px-3 py-1 mb-5 mt-10">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#f5c542] animate-pulse"></span>

                            <span class="font-cinzel text-[10px] text-[#f5c542] uppercase tracking-[0.2em] font-semibold">
                            Live Now · {{ number_format($livePlayers) }} Players
                        </span>
                        </div>
                    @endif

                    {{-- Tagline --}}
                    {{-- Headline — Cinzel, strong hierarchy --}}
                    <h1 class="font-cinzel font-black text-[2rem] sm:text-[2.5rem] md:text-[3rem] lg:text-[3.5rem] leading-[1.05] tracking-tight mb-4">
                        <span class="block text-white" style="text-shadow: 0 0 30px rgba(245,197,66,0.35);">Play Kadi Online</span>
                        <span class="block text-[#f5c542]" style="text-shadow: 0 0 30px rgba(245,197,66,0.35);">Fortune Favors the Bold</span>
                    </h1>

                    {{-- Subline --}}
                    <p class="text-gray-400 text-sm md:text-base leading-relaxed mb-7 max-w-sm">
                        Play the Kadi card game online, anytime, anywhere. Enter tournaments, challenge skilled opponents, and master Kadi.
                    </p>

                    {{-- CTA — gold used sparingly, game-authentic --}}
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-4">
                        @auth
                            <button type="button"
                                    onclick="document.getElementById('kadiPlayForm').submit()"
                                    @if ($this->hasUnverifiedEmail) disabled @endif
                                    class="font-cinzel inline-flex items-center gap-2.5
                                      rounded-full px-7 py-3.5 text-sm font-bold
                                      bg-[#f5c542] text-[#0a0a0a]
                                      hover:bg-[#ffde74]
                                      {{ $this->hasUnverifiedEmail ? 'opacity-50 cursor-not-allowed' : 'hover:-translate-y-0.5' }}
                                      transition-all duration-300">
                                Play Kadi
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </button>

                            @include('partials.kadi-play-form')

                            {{-- Phones/tablets: the nav pill is desktop-only and the menu entry is easy to miss. --}}
                            <x-pwa.install-button variant="hero" />
                        @else
                            <a href="{{ route('login') }}"
                               class="font-cinzel inline-flex items-center gap-2.5
                                  rounded-full px-7 py-3.5 text-sm font-bold
                                  bg-[#f5c542] text-[#0a0a0a]
                                  hover:bg-[#ffde74]
                                  hover:-translate-y-0.5 transition-all duration-300">
                                Sign Up Free to Play
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        @endauth
                    </div>

                    {{-- Trust indicators — simplified, game-authentic minimal style --}}
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-x-5 gap-y-2 mt-8">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-white/40" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
                            </svg>
                            <span class="font-cinzel text-[11px] text-white/40">Licensed: BK-0001273 </span>
                        </div>
                        @if (filled(config('kadi.paybill')))
                        <div class="w-px h-3 bg-white/10"></div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-white/40" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18c-1.69 0-3.267-.507-4.575-1.375l-.325-.2-2.875.855.855-2.875-.2-.325A7.963 7.963 0 0 1 4 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/>
                            </svg>
                            <span class="font-cinzel text-[11px] text-white/40" data-test="welcome-paybill">M-PESA PAYBILL: {{ config('kadi.paybill') }}</span>
                        </div>
                        @endif
                    </div>

                </div>

                {{-- ══ RIGHT: Prize Pool ══ --}}
                <div class="relative">

                    <div class="relative bg-gradient-to-b from-[#1a1200]/80 to-[#0a0a0a]/60
                                border border-[#f5c542]/15 rounded-2xl p-6 backdrop-blur-sm"
                         style="box-shadow: 0 0 40px rgba(245,197,66,0.06), inset 0 1px 0 rgba(245,197,66,0.1);">


                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <div class="font-cinzel text-[9px] text-[#f5c542]/50 uppercase tracking-[0.3em]">Jackpot</div>
                                <div class="font-cinzel text-sm font-bold text-[#f5c542] tracking-wider">Kadi Prize Pool</div>
                            </div>
                            <svg class="w-8 h-8 text-[#f5c542]/20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7 2v2H2v6c0 2.21 1.79 4 4 4h.5c.8 2 2.3 3.5 4.5 3.94V20H7v2h10v-2h-4v-2.06c2.2-.44 3.7-1.94 4.5-3.94H18c2.21 0 4-1.79 4-4V4h-5V2H7zm-1 2v4H4V6h2zm12 4V4h2v4h-2z"/>
                            </svg>
                        </div>

                        <div class="h-px bg-gradient-to-r from-transparent via-[#f5c542]/20 to-transparent mb-5"></div>
                        @php
                            $prizes = [
                                ['rank'=>1,'label'=>'1st Place',            'icon'=>'medal-first-place','amount'=> 110452969, 'color'=>'#FFD700','glow'=>'rgba(255,215,0,0.4)'],
                                ['rank'=>2,'label'=>'Runner-Up',            'icon'=>'medal-second-place','amount'=>  25016339, 'color'=>'#C0C0C0','glow'=>'rgba(192,192,192,0.3)'],
                                ['rank'=>3,'label'=>'Semis (x2 Losers)',    'icon'=>'medal-third-place','amount'=>   9978584, 'color'=>'#CD7F32','glow'=>'rgba(205,127,50,0.3)'],
                                ['rank'=>4,'label'=>'Quarters (x4 Losers)', 'icon'=>'target-02','amount'=>   3086378, 'color'=>'#60a5fa','glow'=>'rgba(96,165,250,0.25)'],
                            ];
                        @endphp

                        <div
                            x-data="{
                                prizes: @js($prizes),
                                displayed: [0,0,0,0],
                                started: false,
                                startCounting() {
                                    if (this.started) return;
                                    this.started = true;
                                    this.prizes.forEach((prize, i) => {
                                        const target = prize.amount;
                                        const steps = 65;
                                        let step = 0;
                                        const iv = setInterval(() => {
                                            step++;
                                            const eased = 1 - Math.pow(1 - step/steps, 3);
                                            this.displayed[i] = Math.round(target * eased);
                                            this.displayed = [...this.displayed];
                                            if (step >= steps) { clearInterval(iv); this.displayed[i] = target; this.displayed = [...this.displayed]; }
                                        }, 2200 / steps);
                                    });
                                },
                                fmt(n) { return Math.round(n).toLocaleString(); }
                            }"
                            x-intersect.once="startCounting()"
                            class="space-y-3"
                        >
                            @foreach($prizes as $i => $prize)
                                <div class="flex items-center gap-3">
                                    <span class="flex-shrink-0 leading-none"
                                          style="color: {{ $prize['color'] }}; filter: drop-shadow(0 0 8px {{ $prize['glow'] }});">
                                        @svg('hugeicons-'.$prize['icon'], 'size-6')
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[10px] font-semibold uppercase tracking-widest"
                                             style="color: {{ $prize['color'] }}; opacity: 0.6;">
                                            {{ $prize['label'] }}
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="font-cinzel font-black text-sm md:text-base leading-none"
                                             style="color: {{ $prize['color'] }}; text-shadow: 0 0 12px {{ $prize['glow'] }};"
                                             :x-text="'KES ' + fmt(displayed[{{ $i }}])"
                                             x-text="'KES {{ number_format($prize['amount']) }}'">
                                        </div>
                                    </div>
                                </div>
                                @if($i < 3)
                                    <div class="h-px bg-gradient-to-r from-transparent via-white/5 to-transparent"></div>
                                @endif
                            @endforeach
                        </div>

                        <div class="mt-5 text-center">
                            <span class="text-[10px] text-gray-700 font-cinzel tracking-widest uppercase">
                                Tournament Terms Apply
                            </span>
                        </div>
                    </div>
                    {{--
                     <div class="absolute -top-2 -right-2 w-16 h-16 rounded-full pointer-events-none"
                         style="background: radial-gradient(circle at top right, rgba(245,197,66,0.15), transparent 70%);">
                    </div>
                    --}}
                </div>

                {{-- ══ RIGHT: Kadi Table Showcase ══
                <div class="relative flex flex-col items-center justify-center py-6 md:py-0">

                    {{-- Ambient glow
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="absolute w-72 h-72 md:w-96 md:h-96 rounded-full bg-[#f5c542]/10 blur-3xl" style="animation: ambientFloat 8s ease-in-out infinite;"></div>
                        <div class="absolute w-56 h-56 md:w-72 md:h-72 rounded-full bg-amber-400/8 blur-[80px]" style="animation: ambientFloat 10s ease-in-out infinite reverse;"></div>
                        <div class="absolute w-40 h-40 md:w-52 md:h-52 rounded-full bg-yellow-200/10 blur-[50px]"></div>
                    </div>

                    {{-- Orbiting suit / chip art around the centerpiece
                    <img src="{{ asset('casino/crown.png') }}" alt="" width="64" height="64" loading="lazy" decoding="async"
                         class="hero-orbit-icon absolute top-0 left-2 md:left-6 w-12 md:w-16 object-contain" style="animation-duration:7s;" />
                    <img src="{{ asset('casino/poker.png') }}" alt="" width="72" height="72" loading="lazy" decoding="async"
                         class="hero-orbit-icon absolute bottom-6 right-0 md:right-2 w-16 md:w-20 object-contain" style="animation-duration:8.5s; animation-delay:.6s;" />
                    <img src="{{ asset('casino/dice.png') }}" alt="" width="56" height="56" loading="lazy" decoding="async"
                         class="hero-orbit-icon absolute bottom-2 left-0 w-11 md:w-14 object-contain" style="animation-duration:6.5s; animation-delay:1.1s;" />
                    <img src="{{ asset('casino/diamond.png') }}" alt="" width="56" height="56" loading="lazy" decoding="async"
                         class="hero-orbit-icon absolute top-6 right-0 md:right-6 w-11 md:w-14 object-contain" style="animation-duration:7.5s; animation-delay:.3s;" />
                    <img src="{{ asset('casino/cherry.png') }}" alt="" width="48" height="48" loading="lazy" decoding="async"
                         class="hero-orbit-icon absolute top-1/2 -left-2 w-10 md:w-12 object-contain hidden sm:block" style="animation-duration:9s; animation-delay:1.6s;" />

                    {{-- Centerpiece medallion
                    <div class="relative z-10 w-40 h-40 md:w-52 md:h-52 rounded-full flex items-center justify-center
                                bg-gradient-to-b from-[#1a1200] to-[#0a0a0a] border-2 border-[#f5c542]/40"
                         style="box-shadow: 0 0 60px rgba(245,197,66,0.22), inset 0 1px 0 rgba(245,197,66,0.15);">
                        <img src="{{ asset('casino/kadi.png') }}" alt="Kadi"
                             width="176" height="176" loading="eager" fetchpriority="high" decoding="async"
                             class="w-28 h-28 md:w-36 md:h-36 object-contain" style="filter: drop-shadow(0 0 22px rgba(245,197,66,0.4));" />
                    </div>

                    {{-- Caption card
                    <div class="relative z-10 mt-6 w-full max-w-xs">
                        <div class="glass-card px-5 py-4 text-center">
                            <div class="font-cinzel text-xs font-bold tracking-widest text-[#f5c542] uppercase mb-1">
                                Every Hand Counts
                            </div>
                            <p class="text-[11px] text-[#6b6b6b] leading-relaxed">
                                Real Kadi tables, real opponents, no waiting.
                            </p>
                            <div class="mt-3 flex items-center justify-center gap-2 text-[10px] font-cinzel tracking-wider text-[#f5f5f0]/50">
                                <span>♠</span><span>♥</span><span>♦</span><span>♣</span>
                            </div>
                        </div>
                    </div>
                </div>
                --}}
            </div>
        </div>

    </section>

    {{-- ===================== FEATURED GAMES (commented out: Kadi-only focus) =====================
    <section id="games" class="py-10 bg-[#0a0a0a]">
        @php
            $featuredGames = app(\App\Services\GamesService::class)->all()->take(10)->values()->toArray();
        @endphp

        <x-carousel
            title="Featured Casino Games"
            subtitle="Top picks from our casino floor"
            view-all-link="{{ route('guest.games') }}"
        >
            @foreach($featuredGames as $game)
                <div class="group relative rounded-2xl overflow-hidden cursor-pointer
                            flex-shrink-0 w-48 sm:w-56 h-64 bg-[#111]
                            border border-[#222] hover:border-[#f5c542]/40 transition-all duration-300
                            snap-start shadow-lg hover:shadow-[0_8px_30px_rgba(245,197,66,0.12)]">
                    <img src="{{ asset($game['path']) }}" alt="{{ $game['name'] }}"
                         width="{{ $game['width'] }}" height="{{ $game['height'] }}"
                         class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                         loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                         fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                         decoding="async" />
                    <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/90 to-transparent pt-12 pb-4 px-4">
                        <a href="#" class="inline-flex items-center gap-1 text-[11px] font-bold text-black bg-[#f5c542] hover:bg-[#ffde74] px-2.5 py-1.5 rounded">Play Now</a>
                    </div>
                </div>
            @endforeach
        </x-carousel>
    </section>
    --}}

    {{-- ===================== POPULAR GAMES (commented out: Kadi-only focus) =====================
    <section id="popular" class="py-10 bg-[#0d0d0d]">
        @php
            $popularGames = app(\App\Services\GamesService::class)->all()->slice(-10)->values()->toArray();
        @endphp

        <x-carousel
            title="Popular Casino Games"
            subtitle="Popular picks from our casino floor"
            view-all-link="{{ route('guest.games') }}"
        >
            @foreach($popularGames as $game)
                <div class="group relative rounded-2xl overflow-hidden cursor-pointer
                            flex-shrink-0 w-48 sm:w-56 h-64 bg-[#111]
                            border border-[#222] hover:border-[#f5c542]/40 transition-all duration-300
                            snap-start shadow-lg hover:shadow-[0_8px_30px_rgba(245,197,66,0.12)]">
                    <img src="{{ asset($game['path']) }}" alt="{{ $game['name'] }}"
                         width="{{ $game['width'] }}" height="{{ $game['height'] }}"
                         class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                         loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                         fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                         decoding="async" />
                    <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/90 to-transparent pt-12 pb-4 px-4">
                        <a href="#" class="inline-flex items-center gap-1 text-[11px] font-bold text-black bg-[#f5c542] hover:bg-[#ffde74] px-2.5 py-1.5 rounded">Play Now</a>
                    </div>
                </div>
            @endforeach
        </x-carousel>
    </section>
    --}}

    {{-- ===================== SPORTS BETTING (commented out: Kadi-only focus) =====================
    <section class="py-10 bg-[#0a0a0a]">
        @php
            $sportsbookCards = [
                ['league' => 'Premier League', 'time' => 'Today 20:00',    'home' => 'Manchester City', 'away' => 'Arsenal',       'odds' => ['2.10', '3.40', '3.20']],
                ['league' => 'La Liga',        'time' => 'Tomorrow 21:00', 'home' => 'Barcelona',       'away' => 'Real Madrid',   'odds' => ['2.50', '3.10', '2.80']],
                ['league' => 'NBA',            'time' => 'Today 02:30',    'home' => 'LA Lakers',       'away' => 'Boston Celtics','odds' => ['1.85', '1.95']],
                ['league' => 'Serie A',        'time' => 'Sat 19:45',      'home' => 'AC Milan',        'away' => 'Juventus',      'odds' => ['2.30', '3.20', '2.90']],
                ['league' => 'Bundesliga',     'time' => 'Sat 17:30',      'home' => 'Bayern Munich',   'away' => 'Dortmund',      'odds' => ['1.75', '3.80', '4.50']],
            ];
        @endphp

        <x-carousel
            title="Sports Betting"
            subtitle="Live odds — updated every 2 hours"
            view-all-link="{{ route('sportsbook') }}"
            view-all-text="Full Sportsbook"
        >
            @foreach($sportsbookCards as $card)
                <div class="flex-shrink-0 w-64 snap-start bg-[#111] border border-[#222] rounded-xl p-4 hover:border-[#f5c542]/40 transition-all duration-200">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-[10px] text-[#f5c542] font-bold uppercase tracking-wide bg-[#1a1200] px-2 py-0.5 rounded">{{ $card['league'] }}</span>
                        <span class="text-[10px] text-gray-500">{{ $card['time'] }}</span>
                    </div>
                    <div class="text-center mb-3">
                        <div class="text-white font-bold text-sm">{{ $card['home'] }}</div>
                        <div class="text-gray-600 text-xs my-1">vs</div>
                        <div class="text-white font-bold text-sm">{{ $card['away'] }}</div>
                    </div>
                    <a href="{{ route('sportsbook') }}" class="block w-full text-center bg-[#f5c542] text-black font-bold py-2 rounded hover:bg-[#ffde74] transition text-xs">Bet Now</a>
                </div>
            @endforeach
            <div class="flex-shrink-0 w-48 snap-start flex flex-col items-center justify-center bg-[#111] border border-dashed border-[#333] rounded-xl p-6 text-center">
                <div class="text-3xl mb-2">🏆</div>
                <div class="text-gray-400 text-sm font-semibold mb-3">More Sports</div>
                <a href="{{ route('sportsbook') }}" class="text-xs font-bold text-black bg-[#f5c542] hover:bg-[#ffde74] px-4 py-2 rounded transition">View All</a>
            </div>
        </x-carousel>
    </section>
    --}}

    {{-- ===================== WHY CHOOSE US =====================
    <section id="about" class="py-24" style="background-color:#111111;background-image:repeating-linear-gradient(45deg,transparent,transparent 40px,rgba(245,197,66,0.03) 40px,rgba(245,197,66,0.03) 41px);">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-16 text-center">
                <h2 class="text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">WHY CHOOSE US</h2>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                @php
                    $features = [
                        ['icon' => '🔒', 'title' => 'Secure & Licensed',  'desc' => '256-bit SSL encryption. Fully licensed and regulated.'],
                        ['icon' => '⚡', 'title' => 'Instant Payouts',    'desc' => 'Withdraw your winnings within minutes, not days.'],
                        ['icon' => '🎁', 'title' => 'Daily Bonuses',      'desc' => 'New rewards, free spins, and cashback every single day.'],
                    ];
                @endphp

                @foreach ($features as $feature)
                    <div class="glass-card glass-card-hover p-8 text-center">
                        <div class="mb-6 text-5xl">{{ $feature['icon'] }}</div>
                        <h3 class="mb-3 text-xl font-semibold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">{{ $feature['title'] }}</h3>
                        <p class="text-[#6b6b6b]" style="font-family: 'Outfit', sans-serif;">{{ $feature['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    --}}

    {{-- ===================== PROMOTIONS BANNER =====================
    <section id="promotions" class="py-20" style="background: linear-gradient(135deg, #1a1000, #2a1f00, #1a1000);">
        <div class="mx-auto max-w-7xl px-6">
            <div class="glass-card flex flex-col items-start gap-6 border-l-4 border-[#f5c542] p-8 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="mb-3 inline-flex rounded-full border border-[#f5c542]/40 bg-[#f5c542]/10 px-3 py-1 text-xs tracking-widest text-[#f5c542]">
                        🎁 WELCOME OFFER
                    </div>
                    <h2 class="mb-2 text-3xl font-bold text-[#f5c542] md:text-4xl" style="font-family: 'Cinzel', serif;">
                        Earn Bonus Coins As You Play
                    </h2>
                    <p class="text-[#f5f5f0]/60" style="font-family: 'Outfit', sans-serif;">Earn bonus coins by watching ads and playing mini-games. T&Cs apply.</p>
                </div>
                @auth
                    <form action="{{ route('play') }}" method="POST" target="_blank" class="shrink-0 inline">
                        @csrf
                        <input type="hidden" name="ggid" value="{{ $googleId }}">
                        <button type="submit"
                           @if ($this->hasUnverifiedEmail) disabled @endif
                           class="btn-casino-primary inline-block rounded-full px-8 py-4 no-underline {{ $this->hasUnverifiedEmail ? 'opacity-50 cursor-not-allowed' : '' }}">
                            Play Kadi →
                        </button>
                    </form>
                @else
                    <a href="{{ route('register') }}" wire:navigate
                       class="btn-casino-primary shrink-0 inline-block rounded-full px-8 py-4 no-underline">
                        Sign Up →
                    </a>
                @endauth

            </div>
        </div>
    </section>
    --}}

    {{-- ===================== STATS BAR ===================== --}}
    <section class="border-y border-[#f5c542]/30 bg-black py-12">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                @php

                    $stats = [
                        ['value' => (string) (6000 + $users), 'label' => 'Community Members'],
                        ['value' => '10',    'label' => 'Game Modes'],
                        ['value' => 'Instant', 'label' => 'M-Pesa Payouts'],
                        ['value' => '24/7',   'label' => 'Support'],
                    ];
                @endphp

                @foreach ($stats as $i => $stat)
                    <div class="flex flex-col items-center gap-2 text-center {{ $i < count($stats) - 1 ? 'md:border-r md:border-[#f5c542]/20' : '' }}">
                        <div class="text-3xl font-bold text-[#f5c542] md:text-4xl" style="font-family: 'Cinzel', serif;">{{ $stat['value'] }}</div>
                        <div class="text-sm text-[#6b6b6b]" style="font-family: 'Outfit', sans-serif;">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== ABOUT (crawlable copy for "kadi online", "kadi play", "kadi game", "kadi rules") ===================== --}}
    <section id="about-kadi" class="bg-[#0a0a0a] py-16 md:py-20">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <h2 class="mb-4 text-3xl font-bold text-[#f5f5f0] md:text-4xl" style="font-family: 'Cinzel', serif;">
                Kadi Online: Kenya's Favourite Card Game
            </h2>
            <p class="mx-auto max-w-2xl text-sm leading-relaxed text-gray-400 md:text-base">
                Kadi is the classic Kenyan card game, now playable online against real opponents. New to the game? Read the
                <a href="{{ route('rules') }}" wire:navigate class="text-[#f5c542] underline hover:text-[#ffde74]">Kadi rules</a>
                to learn how to play, see every mode in the
                <a href="{{ route('game-guide') }}" wire:navigate class="text-[#f5c542] underline hover:text-[#ffde74]">Kadi games and prizes guide</a>,
                or check the
                <a href="{{ route('faq') }}" wire:navigate class="text-[#f5c542] underline hover:text-[#ffde74]">Kadi FAQ</a>.
            </p>
        </div>
    </section>
</div>
