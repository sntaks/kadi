<div x-data="{ activeTab: 'single2' }">

    {{-- ===================== HERO ===================== --}}
    <section class="relative overflow-hidden bg-[#0a0a0a] min-h-[380px] md:min-h-[440px] flex items-center">

        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full pointer-events-none"
             style="background: radial-gradient(circle, rgba(245,197,66,0.08) 0%, transparent 70%);"></div>

        @php
            $casinoImages = cache()->remember('welcome_casino_bg_images', 3600, function () {
                return array_slice(glob(public_path('images/casino/*.{png,jpg,webp}'), GLOB_BRACE), 0, 4);
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
            <img src="{{ asset('images/casino/' . basename($imgPath)) }}"
                 alt="" width="208" height="208"
                 class="absolute {{ $pos }} {{ $rot }} opacity-[0.08] pointer-events-none select-none w-40 md:w-52 object-contain"
                 loading="lazy" decoding="async" aria-hidden="true" />
        @endforeach

        <div class="relative z-10 w-full max-w-5xl mx-auto px-6 py-14 md:py-20 text-center">
            <div class="inline-flex items-center gap-2 bg-[#f5c542]/10 border border-[#f5c542]/20 rounded-full px-4 py-1.5 mb-6">
                <span class="w-1.5 h-1.5 rounded-full bg-[#f5c542] animate-pulse"></span>
                <span class="font-cinzel text-[10px] text-[#f5c542] uppercase tracking-[0.2em] font-semibold">Game Modes &amp; Prizes</span>
            </div>

            <h1 class="font-cinzel font-black text-3xl md:text-5xl text-[#f5c542] leading-tight tracking-wide mb-3"
                style="text-shadow: 0 0 30px rgba(245,197,66,0.35);">
                KADI GAME MODES &amp; PRIZES
            </h1>
            <p class="text-gray-400 text-sm md:text-base leading-relaxed max-w-xl mx-auto">
                Play. Win. Repeat. From head-to-head Kadi singles to high-stakes jackpots, pick your game, place your bet, and take home real winnings.
            </p>
        </div>
    </section>

    @include('partials.quick-nav', ['links' => [
        ['href' => '#how-it-works', 'label' => 'How It Works'],
        ['href' => '#single', 'label' => 'Single Games'],
        ['href' => '#tournaments', 'label' => 'Tournaments'],
        ['href' => '#jackpots', 'label' => 'Jackpots'],
    ]])

    {{-- ===================== HOW IT WORKS ===================== --}}
    <section id="how-it-works" class="scroll-mt-36 py-16 md:py-20" style="background-color:#111111;background-image:repeating-linear-gradient(45deg,transparent,transparent 40px,rgba(245,197,66,0.03) 40px,rgba(245,197,66,0.03) 41px);">
        <div class="mx-auto max-w-6xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">How It Works</h2>
                <p class="mt-3 text-sm text-[#6b6b6b] max-w-xl mx-auto">Every ticket follows the same simple idea, whether it's a quick single game or a multi-round Jackpot.</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                @foreach ([
                    ['icon' => 'ticket-01', 'title' => 'Buy a Ticket', 'desc' => 'One ticket is one attempt: a single game, a tournament run, or a Jackpot campaign. No extra lives: lose it, and you buy a fresh ticket to try again.'],
                    ['icon' => 'fencing', 'title' => 'Win to Advance', 'desc' => 'Single games finish in one match. Tournaments and Jackpots move you forward one 1 vs 1 win at a time, level by level.'],
                    ['icon' => 'floppy-disk', 'title' => 'Progress Is Saved', 'desc' => 'Stepped away mid-tournament or mid-Jackpot? No problem, your level is remembered. Come back in an hour or next week and you\'ll face other players at your same stage.'],
                    ['icon' => 'champion', 'title' => 'Claim Your Prize', 'desc' => 'Reach the final stage and the prize is yours, paid straight into your wallet.'],
                ] as $step)
                    <div class="glass-card glass-card-hover p-6">
                        <div class="text-[#f5c542] mb-4">@svg('hugeicons-'.$step['icon'], 'size-9')</div>
                        <h3 class="font-cinzel text-sm font-bold text-[#f5c542] uppercase tracking-wide mb-2">{{ $step['title'] }}</h3>
                        <p class="text-xs text-[#6b6b6b] leading-relaxed">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== SINGLE GAMES ===================== --}}
    <section id="single" class="scroll-mt-36 py-16 md:py-20 bg-[#0a0a0a]">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mb-12 text-center">
                <div class="font-cinzel text-[10px] text-[#f5c542]/60 uppercase tracking-[0.25em] mb-2">Head to Head</div>
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Single Games</h2>
                <p class="mt-3 text-sm text-[#6b6b6b] max-w-xl mx-auto">Challenge a single opponent. Pick your stake, win big. The more players at the table, the higher the payout.</p>
                <div class="mt-5 inline-flex items-center gap-2.5 rounded-full border border-[#f5c542]/15 bg-white/[0.03] px-4 py-2 text-xs text-[#f5f5f0]/70">
                    <span>@svg('hugeicons-ticket-01', 'size-4')</span>
                    One ticket, one game. Win or lose, grab another ticket anytime to play again.
                </div>
            </div>

            {{-- Mode selector pills --}}
            <div class="flex flex-wrap items-center justify-center gap-3 mb-10">
                <button @click="activeTab = 'single2'"
                        :class="activeTab === 'single2' ? 'border-[#f5c542] bg-[#f5c542]/10 text-[#f5c542] shadow-[0_0_16px_rgba(245,197,66,0.2)]' : 'border-[#f5c542]/15 text-[#f5f5f0]/60'"
                        class="flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-semibold transition-all duration-200">
                    <span>@svg('hugeicons-fencing', 'size-4')</span> vs 2
                </button>
                <button @click="activeTab = 'single3'"
                        :class="activeTab === 'single3' ? 'border-[#f5c542] bg-[#f5c542]/10 text-[#f5c542] shadow-[0_0_16px_rgba(245,197,66,0.2)]' : 'border-[#f5c542]/15 text-[#f5f5f0]/60'"
                        class="flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-semibold transition-all duration-200">
                    <span>@svg('hugeicons-user-group', 'size-4')</span> vs 3
                </button>
                <button @click="activeTab = 'single4'"
                        :class="activeTab === 'single4' ? 'border-[#f5c542] bg-[#f5c542]/10 text-[#f5c542] shadow-[0_0_16px_rgba(245,197,66,0.2)]' : 'border-[#f5c542]/15 text-[#f5f5f0]/60'"
                        class="flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-semibold transition-all duration-200">
                    <span>@svg('hugeicons-castle', 'size-4')</span> vs 4
                </button>
            </div>

            {{-- vs 2 --}}
            <div x-show="activeTab === 'single2'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="glass-card overflow-hidden border-l-4 !border-l-[#f5c542]">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-[#f5c542]/10">
                        <span class="text-[#f5c542]">@svg('hugeicons-fencing', 'size-6')</span>
                        <div>
                            <h3 class="font-cinzel font-bold text-[#f5c542] text-sm uppercase tracking-wide">2-Player Match</h3>
                            <p class="text-xs text-[#6b6b6b]">Winner takes all: 1.9× your stake</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#f5c542]/10">
                                    <th class="px-6 py-3 text-left font-cinzel text-[10px] text-[#f5c542]/60 uppercase tracking-[0.2em]">Stake</th>
                                    <th class="px-6 py-3 text-right font-cinzel text-[10px] text-[#f5c542]/60 uppercase tracking-[0.2em]">Winner Gets</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $vs2 = [
                                        ['stake' => 20, 'win' => 38],
                                        ['stake' => 50, 'win' => 95],
                                        ['stake' => 100, 'win' => 190],
                                        ['stake' => 250, 'win' => 475],
                                        ['stake' => 500, 'win' => 950],
                                        ['stake' => 1000, 'win' => 1900],
                                    ];
                                @endphp
                                @foreach($vs2 as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#f5c542]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-6 py-3.5 text-[#f5f5f0]/80 font-semibold">KSh {{ number_format($row['stake']) }}</td>
                                        <td class="px-6 py-3.5 text-right font-cinzel font-bold text-[#f5c542]">KSh {{ number_format($row['win']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- vs 3 --}}
            <div x-show="activeTab === 'single3'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="glass-card overflow-hidden border-l-4 !border-l-[#60a5fa]">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-[#60a5fa]/10">
                        <span class="text-[#60a5fa]">@svg('hugeicons-user-group', 'size-6')</span>
                        <div>
                            <h3 class="font-cinzel font-bold text-[#60a5fa] text-sm uppercase tracking-wide">3-Player Match</h3>
                            <p class="text-xs text-[#6b6b6b]">Outplay two rivals: 2.85× your stake</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#60a5fa]/10">
                                    <th class="px-6 py-3 text-left font-cinzel text-[10px] text-[#60a5fa]/60 uppercase tracking-[0.2em]">Stake</th>
                                    <th class="px-6 py-3 text-right font-cinzel text-[10px] text-[#60a5fa]/60 uppercase tracking-[0.2em]">Winner Gets</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $vs3 = [
                                        ['stake' => 20, 'win' => '57.00'],
                                        ['stake' => 50, 'win' => '142.50'],
                                        ['stake' => 100, 'win' => '285.00'],
                                        ['stake' => 250, 'win' => '712.50'],
                                        ['stake' => 500, 'win' => '1,425.00'],
                                        ['stake' => 1000, 'win' => '2,850.00'],
                                    ];
                                @endphp
                                @foreach($vs3 as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#60a5fa]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-6 py-3.5 text-[#f5f5f0]/80 font-semibold">KSh {{ number_format($row['stake']) }}</td>
                                        <td class="px-6 py-3.5 text-right font-cinzel font-bold text-[#60a5fa]">KSh {{ $row['win'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- vs 4 --}}
            <div x-show="activeTab === 'single4'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="glass-card overflow-hidden border-l-4 !border-l-[#c084fc]">
                    <div class="flex items-center gap-3 px-6 py-4 border-b border-[#c084fc]/10">
                        <span class="text-[#c084fc]">@svg('hugeicons-castle', 'size-6')</span>
                        <div>
                            <h3 class="font-cinzel font-bold text-[#c084fc] text-sm uppercase tracking-wide">4-Player Match</h3>
                            <p class="text-xs text-[#6b6b6b]">Dominate the table: 3.8× your stake</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#c084fc]/10">
                                    <th class="px-6 py-3 text-left font-cinzel text-[10px] text-[#c084fc]/60 uppercase tracking-[0.2em]">Stake</th>
                                    <th class="px-6 py-3 text-right font-cinzel text-[10px] text-[#c084fc]/60 uppercase tracking-[0.2em]">Winner Gets</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $vs4 = [
                                        ['stake' => 20, 'win' => 76],
                                        ['stake' => 50, 'win' => 190],
                                        ['stake' => 100, 'win' => 380],
                                        ['stake' => 250, 'win' => 950],
                                        ['stake' => 500, 'win' => 1900],
                                        ['stake' => 1000, 'win' => 3800],
                                    ];
                                @endphp
                                @foreach($vs4 as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#c084fc]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-6 py-3.5 text-[#f5f5f0]/80 font-semibold">KSh {{ number_format($row['stake']) }}</td>
                                        <td class="px-6 py-3.5 text-right font-cinzel font-bold text-[#c084fc]">KSh {{ number_format($row['win']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== TOURNAMENTS ===================== --}}
    <section id="tournaments" class="scroll-mt-36 py-16 md:py-20" style="background-color:#111111;background-image:repeating-linear-gradient(45deg,transparent,transparent 40px,rgba(245,197,66,0.03) 40px,rgba(245,197,66,0.03) 41px);">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Tournaments</h2>
                <p class="mt-3 text-sm text-[#6b6b6b] max-w-xl mx-auto">Battle through multiple rounds of elimination. The deeper you go, the bigger the multiplier, up to ×28.8.</p>
                <div class="mt-5 inline-flex items-center gap-2.5 rounded-full border border-[#f5c542]/15 bg-white/[0.03] px-4 py-2 text-xs text-[#f5f5f0]/70">
                    <span>@svg('hugeicons-floppy-disk', 'size-4')</span>
                    Win a round to advance. Stop anytime: your progress saves, and you'll return to players at your same level.
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">

                {{-- 3 Rounds --}}
                <div class="relative glass-card overflow-hidden border-t-4 !border-t-[#50C878] group hover:shadow-[0_0_40px_rgba(80,200,120,0.15)] transition-all duration-300">
                    <div class="absolute top-0 right-0 w-32 h-32 pointer-events-none" style="background: radial-gradient(circle at top right, rgba(80,200,120,0.1), transparent 70%);"></div>
                    <div class="px-6 py-6 text-center border-b border-[#50C878]/10 relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#50C878]/10 border-2 border-[#50C878]/40 text-[#50C878] mb-3"
                             style="box-shadow: 0 0 30px rgba(80,200,120,0.2);">@svg('hugeicons-gem', 'size-8')
                        </div>
                        <div class="font-cinzel text-[10px] text-[#50C878]/60 uppercase tracking-[0.25em] mb-1">3 Rounds</div>
                        <h3 class="font-cinzel font-black text-3xl tracking-wide text-[#50C878]">Emerald</h3>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-[#50C878]/10 border border-[#50C878]/30 px-3 py-1 text-xs font-cinzel font-bold text-[#50C878]">×7.2</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#50C878]/10">
                                    <th class="px-5 py-3 text-left font-cinzel text-[10px] text-[#50C878]/60 uppercase tracking-[0.2em]">Stake</th>
                                    <th class="px-5 py-3 text-right font-cinzel text-[10px] text-[#50C878]/60 uppercase tracking-[0.2em]">Wins</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $t3 = [
                                        ['stake' => 20, 'win' => 144],
                                        ['stake' => 50, 'win' => 360],
                                        ['stake' => 100, 'win' => 720],
                                        ['stake' => 250, 'win' => 1800],
                                        ['stake' => 500, 'win' => 3600],
                                        ['stake' => 1000, 'win' => 7200],
                                    ];
                                @endphp
                                @foreach($t3 as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#50C878]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-5 py-3 text-[#f5f5f0]/80">KSh {{ number_format($row['stake']) }}</td>
                                        <td class="px-5 py-3 text-right font-cinzel font-bold text-[#50C878]">KSh {{ number_format($row['win']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 4 Rounds --}}
                <div class="glass-card overflow-hidden border-t-4 !border-t-[#E0115F] group hover:shadow-[0_0_30px_rgba(224,17,95,0.12)] transition-all duration-300">
                    <div class="absolute top-0 right-0 w-32 h-32 pointer-events-none" style="background: radial-gradient(circle at top right, rgba(224,17,95,0.1), transparent 70%);"></div>
                    <div class="px-6 py-6 text-center border-b border-[#E0115F]/10 relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#E0115F]/10 border-2 border-[#E0115F]/40 text-[#E0115F] mb-3"
                             style="box-shadow: 0 0 30px rgba(224,17,95,0.2);">@svg('hugeicons-diamond-01', 'size-8')
                        </div>
                        <div class="font-cinzel text-[10px] text-[#E0115F]/60 uppercase tracking-[0.25em] mb-1">4 Rounds</div>
                        <h3 class="font-cinzel font-black text-3xl tracking-wide text-[#E0115F]">Ruby</h3>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-[#E0115F]/10 border border-[#E0115F]/30 px-3 py-1 text-xs font-cinzel font-bold text-[#E0115F]">×14.4</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#E0115F]/10">
                                    <th class="px-5 py-3 text-left font-cinzel text-[10px] text-[#E0115F]/60 uppercase tracking-[0.2em]">Stake</th>
                                    <th class="px-5 py-3 text-right font-cinzel text-[10px] text-[#E0115F]/60 uppercase tracking-[0.2em]">Wins</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $t4 = [
                                        ['stake' => 20, 'win' => 288],
                                        ['stake' => 50, 'win' => 720],
                                        ['stake' => 100, 'win' => 1440],
                                        ['stake' => 250, 'win' => 3600],
                                        ['stake' => 500, 'win' => 7200],
                                        ['stake' => 1000, 'win' => 14400],
                                    ];
                                @endphp
                                @foreach($t4 as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#E0115F]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-5 py-3 text-[#f5f5f0]/80">KSh {{ number_format($row['stake']) }}</td>
                                        <td class="px-5 py-3 text-right font-cinzel font-bold text-[#E0115F]">KSh {{ number_format($row['win']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 5 Rounds --}}
                <div class="relative glass-card overflow-hidden border-t-4 !border-t-[#B9F2FF] group hover:shadow-[0_0_40px_rgba(185,242,255,0.15)] transition-all duration-300">
                    <div class="absolute top-0 right-0 w-32 h-32 pointer-events-none" style="background: radial-gradient(circle at top right, rgba(185,242,255,0.1), transparent 70%);"></div>
                    <div class="px-6 py-6 text-center border-b border-[#B9F2FF]/10 relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#B9F2FF]/10 border-2 border-[#B9F2FF]/40 text-[#B9F2FF] mb-3"
                             style="box-shadow: 0 0 30px rgba(185,242,255,0.2);">@svg('hugeicons-diamond-02', 'size-8')
                        </div>
                        <div class="font-cinzel text-[10px] text-[#B9F2FF]/60 uppercase tracking-[0.25em] mb-1">5 Rounds</div>
                        <h3 class="font-cinzel font-black text-3xl tracking-wide text-[#B9F2FF]">Diamond</h3>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-[#B9F2FF]/10 border border-[#B9F2FF]/30 px-3 py-1 text-xs font-cinzel font-bold text-[#B9F2FF]">×28.8</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#B9F2FF]/10">
                                    <th class="px-5 py-3 text-left font-cinzel text-[10px] text-[#B9F2FF]/60 uppercase tracking-[0.2em]">Stake</th>
                                    <th class="px-5 py-3 text-right font-cinzel text-[10px] text-[#B9F2FF]/60 uppercase tracking-[0.2em]">Wins</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $t5 = [
                                        ['stake' => 20, 'win' => 576],
                                        ['stake' => 50, 'win' => 1440],
                                        ['stake' => 100, 'win' => 2880],
                                        ['stake' => 250, 'win' => 7200],
                                        ['stake' => 500, 'win' => 14400],
                                        ['stake' => 1000, 'win' => 28800],
                                    ];
                                @endphp
                                @foreach($t5 as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#B9F2FF]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-5 py-3 text-[#f5f5f0]/80">KSh {{ number_format($row['stake']) }}</td>
                                        <td class="px-5 py-3 text-right font-cinzel font-bold text-[#B9F2FF]">KSh {{ number_format($row['win']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ===================== JACKPOTS ===================== --}}
    <section id="jackpots" class="scroll-mt-36 py-16 md:py-20 bg-[#0a0a0a]">
        <div class="mx-auto max-w-6xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Jackpots</h2>
                <p class="mt-3 text-sm text-[#6b6b6b] max-w-xl mx-auto">Multi-table elimination brackets with massive prize pools. Entry fees fund the entire prize structure.</p>
                <div class="mt-5 inline-flex items-center gap-2.5 rounded-full border border-[#f5c542]/15 bg-white/[0.03] px-4 py-2 text-xs text-[#f5f5f0]/70">
                    <span>@svg('hugeicons-floppy-disk', 'size-4')</span>
                    Play at your own pace. Progress saves between games, so pick up right where you left off.
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

                {{-- Bronze JP --}}
                <div class="relative glass-card overflow-hidden border-t-4 !border-t-[#CD7F32] group hover:shadow-[0_0_40px_rgba(205,127,50,0.15)] transition-all duration-300">
                    <div class="absolute top-0 right-0 w-32 h-32 pointer-events-none" style="background: radial-gradient(circle at top right, rgba(205,127,50,0.1), transparent 70%);"></div>
                    <div class="px-6 py-6 text-center border-b border-[#CD7F32]/10 relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#CD7F32]/10 border-2 border-[#CD7F32]/40 text-[#CD7F32] mb-3"
                             style="box-shadow: 0 0 30px rgba(205,127,50,0.2);">
                            @svg('hugeicons-medal-third-place', 'size-8')
                        </div>
                        <div class="font-cinzel text-[10px] text-[#CD7F32]/60 uppercase tracking-[0.25em] mb-1">Bronze Jackpot</div>
                        <h3 class="font-cinzel font-black text-xl text-[#CD7F32]">Bronze JP</h3>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-[#CD7F32]/10 border border-[#CD7F32]/30 px-3 py-1 text-xs font-cinzel font-bold text-[#CD7F32]">KSh 20</span>
                            <span class="inline-flex items-center rounded-full bg-white/5 border border-white/10 px-3 py-1 text-xs text-[#6b6b6b]">13 Games</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#CD7F32]/10">
                                    <th class="px-5 py-3 text-left font-cinzel text-[10px] text-[#CD7F32]/60 uppercase tracking-[0.2em]">Category</th>
                                    <th class="px-5 py-3 text-right font-cinzel text-[10px] text-[#CD7F32]/60 uppercase tracking-[0.2em]">Total Prize</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $bronzeJp = [
                                        ['icon' => 'medal-first-place', 'label' => '1st Place',    'prize' => '86,291'],
                                        ['icon' => 'medal-second-place', 'label' => 'Runner-Up',    'prize' => '19,544'],
                                        ['icon' => 'medal-third-place', 'label' => 'Semis (2)',    'prize' => '7,795'],
                                        ['icon' => 'target-02', 'label' => 'Quarters (4)', 'prize' => '2,441'],
                                    ];
                                @endphp
                                @foreach($bronzeJp as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#CD7F32]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-5 py-3">
                                            <span class="mr-2 inline-flex align-middle text-[#CD7F32]">@svg('hugeicons-'.$row['icon'], 'size-4')</span>
                                            <span class="text-[#f5f5f0]/80">{{ $row['label'] }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right font-cinzel font-bold text-[#CD7F32]">KSh {{ $row['prize'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-[#CD7F32]/5">
                                    <td class="px-5 py-3 font-cinzel font-bold text-[#CD7F32]">TOTAL</td>
                                    <td class="px-5 py-3 text-right font-cinzel font-black text-[#CD7F32] text-base">KSh 131,189</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Silver JP --}}
                <div class="relative glass-card overflow-hidden border-t-4 !border-t-[#C0C0C0] group hover:shadow-[0_0_40px_rgba(192,192,192,0.12)] transition-all duration-300">
                    <div class="absolute top-0 right-0 w-32 h-32 pointer-events-none" style="background: radial-gradient(circle at top right, rgba(192,192,192,0.08), transparent 70%);"></div>
                    <div class="px-6 py-6 text-center border-b border-[#C0C0C0]/10 relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#C0C0C0]/10 border-2 border-[#C0C0C0]/40 text-[#C0C0C0] mb-3"
                             style="box-shadow: 0 0 30px rgba(192,192,192,0.15);">
                            @svg('hugeicons-medal-second-place', 'size-8')
                        </div>
                        <div class="font-cinzel text-[10px] text-[#C0C0C0]/60 uppercase tracking-[0.25em] mb-1">Silver Jackpot</div>
                        <h3 class="font-cinzel font-black text-xl text-[#C0C0C0]">Silver JP</h3>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-[#C0C0C0]/10 border border-[#C0C0C0]/30 px-3 py-1 text-xs font-cinzel font-bold text-[#C0C0C0]">KSh 50</span>
                            <span class="inline-flex items-center rounded-full bg-white/5 border border-white/10 px-3 py-1 text-xs text-[#6b6b6b]">17 Games</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#C0C0C0]/10">
                                    <th class="px-5 py-3 text-left font-cinzel text-[10px] text-[#C0C0C0]/60 uppercase tracking-[0.2em]">Category</th>
                                    <th class="px-5 py-3 text-right font-cinzel text-[10px] text-[#C0C0C0]/60 uppercase tracking-[0.2em]">Total Prize</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $silverJp = [
                                        ['icon' => 'medal-first-place', 'label' => '1st Place',    'prize' => '3,451,655'],
                                        ['icon' => 'medal-second-place', 'label' => 'Runner-Up',    'prize' => '781,760'],
                                        ['icon' => 'medal-third-place', 'label' => 'Semis (2)',    'prize' => '311,830'],
                                        ['icon' => 'target-02', 'label' => 'Quarters (4)', 'prize' => '96,449'],
                                    ];
                                @endphp
                                @foreach($silverJp as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#C0C0C0]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-5 py-3">
                                            <span class="mr-2 inline-flex align-middle text-[#C0C0C0]">@svg('hugeicons-'.$row['icon'], 'size-4')</span>
                                            <span class="text-[#f5f5f0]/80">{{ $row['label'] }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right font-cinzel font-bold text-[#C0C0C0]">KSh {{ $row['prize'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-[#C0C0C0]/5">
                                    <td class="px-5 py-3 font-cinzel font-bold text-[#C0C0C0]">TOTAL</td>
                                    <td class="px-5 py-3 text-right font-cinzel font-black text-[#C0C0C0] text-base">KSh 5,242,871</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Gold JP --}}
                <div class="relative glass-card overflow-hidden border-t-4 !border-t-[#FFD700] group hover:shadow-[0_0_40px_rgba(255,215,0,0.15)] transition-all duration-300">
                    <div class="absolute top-0 right-0 w-32 h-32 pointer-events-none" style="background: radial-gradient(circle at top right, rgba(255,215,0,0.1), transparent 70%);"></div>
                    <div class="px-6 py-6 text-center border-b border-[#FFD700]/10 relative">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#FFD700]/10 border-2 border-[#FFD700]/40 text-[#FFD700] mb-3"
                             style="box-shadow: 0 0 30px rgba(255,215,0,0.2);">
                            @svg('hugeicons-crown', 'size-8')
                        </div>
                        <div class="font-cinzel text-[10px] text-[#FFD700]/60 uppercase tracking-[0.25em] mb-1">Gold Jackpot</div>
                        <h3 class="font-cinzel font-black text-xl text-[#FFD700]">Gold JP</h3>
                        <div class="mt-2 flex items-center justify-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-[#FFD700]/10 border border-[#FFD700]/30 px-3 py-1 text-xs font-cinzel font-bold text-[#FFD700]">KSh 100</span>
                            <span class="inline-flex items-center rounded-full bg-white/5 border border-white/10 px-3 py-1 text-xs text-[#6b6b6b]">21 Games</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#FFD700]/10">
                                    <th class="px-5 py-3 text-left font-cinzel text-[10px] text-[#FFD700]/60 uppercase tracking-[0.2em]">Category</th>
                                    <th class="px-5 py-3 text-right font-cinzel text-[10px] text-[#FFD700]/60 uppercase tracking-[0.2em]">Total Prize</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $goldJp = [
                                        ['icon' => 'medal-first-place', 'label' => '1st Place',    'prize' => '110,452,969'],
                                        ['icon' => 'medal-second-place', 'label' => 'Runner-Up',    'prize' => '25,016,339'],
                                        ['icon' => 'medal-third-place', 'label' => 'Semis (2)',    'prize' => '9,978,584'],
                                        ['icon' => 'target-02', 'label' => 'Quarters (4)', 'prize' => '3,086,378'],
                                    ];
                                @endphp
                                @foreach($goldJp as $row)
                                    <tr class="border-b border-white/5 hover:bg-[#FFD700]/5 transition-colors {{ $loop->last ? 'border-b-0' : '' }}">
                                        <td class="px-5 py-3">
                                            <span class="mr-2 inline-flex align-middle text-[#FFD700]">@svg('hugeicons-'.$row['icon'], 'size-4')</span>
                                            <span class="text-[#f5f5f0]/80">{{ $row['label'] }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right font-cinzel font-bold text-[#FFD700]">KSh {{ $row['prize'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-[#FFD700]/5">
                                    <td class="px-5 py-3 font-cinzel font-bold text-[#FFD700]">TOTAL</td>
                                    <td class="px-5 py-3 text-right font-cinzel font-black text-[#FFD700] text-base">KSh 167,772,160</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ===================== CLOSING CTA ===================== --}}
    <section class="py-16 md:py-20" style="background: linear-gradient(135deg, #1a1000, #2a1f00, #1a1000);">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <div class="mb-4 inline-flex rounded-full border border-[#f5c542]/40 bg-[#f5c542]/10 px-3 py-1 text-xs tracking-widest text-[#f5c542]">
                ♠ READY TO PLAY?
            </div>
            <h2 class="mb-3 text-3xl md:text-4xl font-bold text-[#f5c542]" style="font-family: 'Cinzel', serif;">
                Pick Your Game. Place Your Bet.
            </h2>
            <p class="mb-8 text-[#f5f5f0]/60 max-w-lg mx-auto" style="font-family: 'Outfit', sans-serif;">
                From quick singles to massive jackpots, there's a game mode for every player and every budget.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ route('home') }}"
                       class="btn-casino-primary inline-block rounded-full px-8 py-4 no-underline">
                        Play Kadi →
                    </a>
                @else
                    <a href="{{ route('register') }}" wire:navigate
                       class="btn-casino-primary inline-block rounded-full px-8 py-4 no-underline">
                        Create Account →
                    </a>
                    <a href="{{ route('login') }}" wire:navigate
                       class="btn-casino-ghost inline-block rounded-full px-8 py-4 no-underline">
                        Login
                    </a>
                @endauth
            </div>
        </div>
    </section>

    @include('partials.back-to-top')
</div>
