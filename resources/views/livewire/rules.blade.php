<div x-data="{
        activeCategory: 'play',
        categories: {
            play: {
                label: 'Play Cards', ranks: '4, 5, 6, 7, 9, 10', color: '#f5c542', glow: 'rgba(245,197,66,0.35)', icon: '🂡',
                desc: 'The backbone of every hand. Play a card that matches either the suit or the rank of the top card on the discard pile. No special effects, just tempo and timing. Hold several matching cards? Chain them in one turn, for example 4♠ → 4♥ → 4♦ → 4♣.'
            },
            jump: {
                label: 'Jump Cards', ranks: 'Jack (J)', color: '#60a5fa', glow: 'rgba(96,165,250,0.35)', icon: '🂫',
                desc: 'Skip the next player\'s turn entirely, provided the Jack matches the suit or rank of the top card. Perfect for cutting off an opponent who\'s about to call Kadi.'
            },
            question: {
                label: 'Question Cards', ranks: 'Queen (Q), 8', color: '#c084fc', glow: 'rgba(192,132,252,0.35)', icon: '🂭',
                desc: 'Puts the next player on the spot. They must answer with a matching Play Card (4, 5, 6, 7, 9 or 10) of the same suit or rank. Fail to answer and it\'s straight to the draw pile. Queens and 8s can chain onto each other, but only when they share the same rank.'
            },
            kickback: {
                label: 'Kickback Cards', ranks: 'King (K)', color: '#50e870', glow: 'rgba(80,232,112,0.35)', icon: '🂮',
                desc: 'Reverses the direction of play instantly: clockwise becomes counter-clockwise and vice versa. A single King can flip the entire momentum of the table.'
            },
            penalty: {
                label: 'Penalty Cards', ranks: '2, 3, Joker', color: '#ff6b6b', glow: 'rgba(255,107,107,0.35)', icon: '<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; style=&quot;vertical-align:-0.125em&quot;><path d=&quot;M4 7C5.10457 7 6 6.10457 6 5C6 3.89543 5.10457 3 4 3C2.89543 3 2 3.89543 2 5C2 6.10457 2.89543 7 4 7Z&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;1.5&quot; /><path d=&quot;M20 9C21.1046 9 22 8.10457 22 7C22 5.89543 21.1046 5 20 5C18.8954 5 18 5.89543 18 7C18 8.10457 18.8954 9 20 9Z&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;1.5&quot; /><path d=&quot;M4.96694 17C1.44331 11.5 8.98977 11 4.95922 7M6.37859 5C7.48264 5 9.99986 6 9.99986 8&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-width=&quot;1.5&quot; /><path d=&quot;M5 17C10.2172 18.4523 13.3284 18.2088 19 17&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; /><path d=&quot;M4 20C9.96256 21.4523 13.5182 21.2088 20 20&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; /><path d=&quot;M18.0289 6.04492C15.664 6.47929 10.2102 9.78535 11.0499 17.2385M18.2511 8.0892C17.0361 8.66181 15.1252 9.88778 17.5983 12.9129C18.3787 13.8676 19.2858 15.88 18.951 16.8691&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;1.5&quot; /></svg>',
                desc: 'Force the next player to draw 2, 3, or 5 cards. Chain a matching Penalty Card to pass it along instead of drawing, or neutralize it entirely with an Ace. The black Joker matches Spades &amp; Clubs, and the red Joker matches Hearts &amp; Diamonds. See <a href=\'#chaining\' class=\'underline decoration-dotted underline-offset-4 hover:text-[#f5f5f0]\'>chaining rules</a> below for every valid combo.'
            },
            ace: {
                label: 'Ace Cards', ranks: 'Ace (A)', color: '#ffde74', glow: 'rgba(255,222,116,0.35)', icon: '🂱',
                desc: 'The most versatile card in the deck. Declare a brand new suit for the next player to follow, or use it to neutralize an incoming penalty. Pure strategic control.'
            },
        }
    }">

    {{-- ===================== HERO ===================== --}}
    <section class="relative overflow-hidden bg-[#0a0a0a] min-h-[380px] md:min-h-[440px] flex items-center">

        {{-- Radial gold glow --}}
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full pointer-events-none"
             style="background: radial-gradient(circle, rgba(245,197,66,0.08) 0%, transparent 70%);"></div>

        {{-- Floating suit symbols
        <span class="hero-orbit-icon absolute top-10 left-[8%] text-4xl md:text-5xl text-[#f5c542]/20 select-none" style="animation-duration:7s;">♠</span>
        <span class="hero-orbit-icon absolute bottom-8 left-[20%] text-3xl md:text-4xl text-[#f5c542]/15 select-none" style="animation-duration:8.5s; animation-delay:.6s;">♥</span>
        <span class="hero-orbit-icon absolute top-16 right-[12%] text-4xl md:text-5xl text-[#f5c542]/20 select-none" style="animation-duration:6.5s; animation-delay:1.1s;">♦</span>
        <span class="hero-orbit-icon absolute bottom-10 right-[22%] text-3xl md:text-4xl text-[#f5c542]/15 select-none hidden sm:block" style="animation-duration:9s; animation-delay:.3s;">♣</span>
        --}}

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

        <div class="relative z-10 w-full max-w-5xl mx-auto px-6 py-14 md:py-20 text-center">
            <div class="inline-flex items-center gap-2 bg-[#f5c542]/10 border border-[#f5c542]/20 rounded-full px-4 py-1.5 mb-6">
                <span class="w-1.5 h-1.5 rounded-full bg-[#f5c542] animate-pulse"></span>
                <span class="font-cinzel text-[10px] text-[#f5c542] uppercase tracking-[0.2em] font-semibold">The Official Rulebook</span>
            </div>

            <h1 class="font-cinzel font-black text-3xl md:text-5xl text-[#f5c542] leading-tight tracking-wide mb-3"
                style="text-shadow: 0 0 30px rgba(245,197,66,0.35);">
                KADI RULES: HOW TO PLAY
            </h1>
            <p class="text-gray-400 text-sm md:text-base leading-relaxed max-w-xl mx-auto">
                Four suits. Six card types. One winner. Learn how every hand of Kadi is dealt, played, and won, then take it to the table.
            </p>
        </div>
    </section>

    @include('partials.quick-nav', ['links' => [
        ['href' => '#objective', 'label' => 'Objective'],
        ['href' => '#setup', 'label' => 'Setup & Dealing'],
        ['href' => '#card-types', 'label' => 'Card Types'],
        ['href' => '#chaining', 'label' => 'Chaining'],
        ['href' => '#gameplay', 'label' => 'Gameplay'],
        ['href' => '#winning', 'label' => 'Winning'],
        ['href' => '#strategy', 'label' => 'Strategy'],
    ]])

    {{-- ===================== OBJECTIVE ===================== --}}
    <section id="objective" class="scroll-mt-36 py-16 md:py-20 bg-[#0a0a0a]">
        <div class="mx-auto max-w-5xl px-6">
            <div class="glass-card p-8 md:p-10 flex flex-col md:flex-row items-center gap-8 border-l-4 !border-l-[#f5c542]">
                <div class="flex-shrink-0 w-20 h-20 md:w-24 md:h-24 rounded-full flex items-center justify-center bg-gradient-to-b from-[#1a1200] to-[#0a0a0a] border-2 border-[#f5c542]/40 text-[#f5c542]"
                     style="box-shadow: 0 0 40px rgba(245,197,66,0.2);">
                    <x-hugeicons-target-02 class="size-9 md:size-10" />
                </div>
                <div>
                    <div class="font-cinzel text-[10px] text-[#f5c542]/60 uppercase tracking-[0.25em] mb-2">The Objective</div>
                    <h2 class="font-cinzel font-bold text-xl md:text-2xl text-[#f5f5f0] mb-3">Empty Your Hand First</h2>
                    <p class="text-sm md:text-base text-[#6b6b6b] leading-relaxed">
                        Kadi is a fast-paced Kenyan card game for 2-4 players, played with a standard 54-card deck (Jokers included).
                        Every hand starts even (4 cards each), and the first player to strategically play their <em class="not-italic text-[#f5c542]">entire hand</em>
                        while outmaneuvering opponents' action cards claims victory.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== SETUP & DEALING ===================== --}}
    <section id="setup" class="scroll-mt-36 py-16 md:py-20" style="background-color:#111111;background-image:repeating-linear-gradient(45deg,transparent,transparent 40px,rgba(245,197,66,0.03) 40px,rgba(245,197,66,0.03) 41px);">
        <div class="mx-auto max-w-6xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Setup &amp; Dealing</h2>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                @foreach ([
                    ['icon' => asset('rules/shuffle.png'), 'title' => 'Shuffle & Deal', 'desc' => 'The server shuffles a full 54-card deck (with two Jokers) and deals 4 cards to each player.'],
                    ['icon' => asset('rules/card-draw.png'), 'title' => 'The Draw Pile', 'desc' => 'Remaining cards form a face-down draw pile, managed automatically by the server.'],
                    ['icon' => asset('rules/card-pile.png'), 'title' => 'Start the Discard', 'desc' => 'The top card of the draw pile is flipped face-up to begin the discard pile.'],
                    ['icon' => asset('rules/restrict.png'), 'title' => 'Opening Restrictions', 'desc' => '2s, 3s, Jokers, Queens, 8s, Jacks, Kings & Aces can never start the discard pile; they\'re auto-replaced.'],
                ] as $step)
                    <div class="glass-card glass-card-hover p-6">
                        <div class="text-3xl mb-4">
                            <img src="{{ $step['icon'] }}" alt="{{ $step['title'] }}" width="80" height="80" loading="lazy" decoding="async">
                        </div>
                        <h3 class="font-cinzel text-sm font-bold text-[#f5c542] uppercase tracking-wide mb-2">{{ $step['title'] }}</h3>
                        <p class="text-xs text-[#6b6b6b] leading-relaxed">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== CARD CLASSIFICATIONS (interactive tabs) ===================== --}}
    <section id="card-types" class="scroll-mt-36 py-16 md:py-20 bg-[#0a0a0a]">
        <div class="mx-auto max-w-6xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Card Suits &amp; Classifications</h2>
                <p class="mt-3 text-sm text-[#6b6b6b] max-w-2xl mx-auto">Every card falls into one of six categories. Tap a category to see what it does.</p>
            </div>

            {{-- Category pills --}}
            <div class="flex flex-wrap items-center justify-center gap-2 mb-8">
                <template x-for="(cat, key) in categories" :key="key">
                    <button
                        @click="activeCategory = key"
                        class="flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold transition-all duration-200"
                        :style="activeCategory === key
                            ? `border-color:${cat.color}; background:${cat.color}1a; color:${cat.color}; box-shadow: 0 0 16px ${cat.glow};`
                            : 'border-color: rgba(245,197,66,0.15); color: rgba(245,245,240,0.6);'"
                    >
                        <span x-html="cat.icon" class="text-base leading-none"></span>
                        <span x-text="cat.label"></span>
                    </button>
                </template>
            </div>

            {{-- Active panel --}}
            <div class="glass-card p-8 md:p-10 relative overflow-hidden min-h-[220px]">
                <template x-for="(cat, key) in categories" :key="'panel-'+key">
                    <div x-show="activeCategory === key"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="flex flex-col md:flex-row items-start gap-8">

                        <div class="flex-shrink-0 flex flex-col items-center gap-3">
                            <div class="w-20 h-20 md:w-24 md:h-24 rounded-2xl flex items-center justify-center text-4xl md:text-5xl border-2"
                                 :style="`background: radial-gradient(circle at 50% 35%, ${cat.color}22, transparent); border-color: ${cat.color}55; box-shadow: 0 0 30px ${cat.glow};`">
                                <span x-html="cat.icon"></span>
                            </div>
                            <span class="stat-badge" :style="`color:${cat.color}; border-color:${cat.color}40; background:${cat.color}1a;`" x-text="cat.ranks"></span>
                        </div>

                        <div class="flex-1">
                            <h3 class="font-cinzel font-bold text-xl md:text-2xl mb-3" :style="`color:${cat.color}`" x-text="cat.label"></h3>
                            <p class="text-sm md:text-base text-[#f5f5f0]/70 leading-relaxed" x-html="cat.desc"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>

    {{-- ===================== CHAINING & COMBOS ===================== --}}
    <section id="chaining" class="scroll-mt-36 py-16 md:py-20" style="background-color:#111111;background-image:repeating-linear-gradient(45deg,transparent,transparent 40px,rgba(245,197,66,0.03) 40px,rgba(245,197,66,0.03) 41px);">
        <div class="mx-auto max-w-6xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Chaining &amp; Combos</h2>
                <p class="mt-3 text-sm text-[#6b6b6b] max-w-2xl mx-auto">A single turn isn't limited to one card. Play several compatible cards back to back. Here's what's allowed.</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">

                {{-- Rank chain --}}
                <div class="glass-card glass-card-hover p-6">
                    <div class="text-2xl mb-3">🂡</div>
                    <h3 class="font-cinzel text-sm font-bold text-[#f5c542] uppercase tracking-wide mb-2">Standard Chains</h3>
                    <p class="text-xs text-[#6b6b6b] leading-relaxed mb-4">Each card must match the one before it. A chain ends when you run out of compatible cards, choose to stop, or a card effect forces it to end.</p>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach (['4♠', '4♥', '4♦', '4♣'] as $i => $card)
                            @if($i > 0)<span class="text-[#f5c542]/40 text-xs">→</span>@endif
                            <span class="inline-flex items-center justify-center min-w-[2.25rem] rounded-md border border-[#f5c542]/30 bg-[#f5c542]/5 px-2 py-1 text-xs font-cinzel font-bold text-[#f5c542]">{{ $card }}</span>
                        @endforeach
                    </div>
                </div>

                {{-- Question chain --}}
                <div class="glass-card glass-card-hover p-6">
                    <div class="text-2xl mb-3">🂭</div>
                    <h3 class="font-cinzel text-sm font-bold text-[#c084fc] uppercase tracking-wide mb-2">Question Chains</h3>
                    <p class="text-xs text-[#6b6b6b] leading-relaxed mb-4">Queens and 8s can be chained onto each other, but only when they share the same rank. Once a valid Answer Card is played, the Question ends.</p>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @foreach (['Q♠', 'Q♥', '7♥'] as $i => $card)
                            @if($i > 0)<span class="text-[#c084fc]/40 text-xs">→</span>@endif
                            <span class="inline-flex items-center justify-center min-w-[2.25rem] rounded-md border border-[#c084fc]/30 bg-[#c084fc]/5 px-2 py-1 text-xs font-cinzel font-bold text-[#c084fc]">{{ $card }}</span>
                        @endforeach
                    </div>
                </div>

                {{-- Penalty chain --}}
                <div class="glass-card glass-card-hover p-6">
                    <div class="text-[#ff6b6b] mb-3">@svg('hugeicons-joker', 'size-6')</div>
                    <h3 class="font-cinzel text-sm font-bold text-[#ff6b6b] uppercase tracking-wide mb-2">Penalty Chains</h3>
                    <p class="text-xs text-[#6b6b6b] leading-relaxed mb-4">Pass a penalty on instead of drawing. Only the most recently played Penalty Card's effect applies to whoever can't continue the chain.</p>
                    <ul class="space-y-1.5 text-xs text-[#f5f5f0]/70">
                        <li class="flex items-center gap-1.5"><span class="font-cinzel font-bold text-[#ff6b6b]">2</span><span class="text-[#ff6b6b]/40">→</span><span class="font-cinzel font-bold text-[#ff6b6b]">2</span></li>
                        <li class="flex items-center gap-1.5"><span class="font-cinzel font-bold text-[#ff6b6b]">3</span><span class="text-[#ff6b6b]/40">→</span><span class="font-cinzel font-bold text-[#ff6b6b]">3</span></li>
                        <li>2 or 3 → matching same-suit 2 or 3</li>
                        <li class="flex items-center gap-1.5"><span class="font-cinzel font-bold text-[#ff6b6b]">Joker</span><span class="text-[#ff6b6b]/40">→</span><span class="font-cinzel font-bold text-[#ff6b6b]">Joker</span></li>
                        <li>Red Joker ↔ red 2 or 3</li>
                        <li>Black Joker ↔ black 2 or 3</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== GAMEPLAY MECHANICS ===================== --}}
    <section id="gameplay" class="scroll-mt-36 py-16 md:py-20" style="background: linear-gradient(160deg, #0a0a0a 0%, #120d00 50%, #0a0a0a 100%);">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Gameplay Mechanics</h2>
            </div>

            <div class="relative">
                {{-- Connecting line --}}
                <div class="hidden md:block absolute left-6 top-6 bottom-6 w-px bg-gradient-to-b from-[#f5c542]/50 via-[#f5c542]/20 to-transparent"></div>

                <div class="space-y-6">
                    @foreach ([
                        ['icon' => 'refresh-01', 'title' => 'Play Moves Clockwise', 'desc' => 'Turns begin next to the dealer and proceed clockwise, until a King reverses the direction.'],
                        ['icon' => 'target-02', 'title' => 'Match Suit or Rank', 'desc' => 'On your turn, play a card that matches either the suit or the rank of the top discard pile card, or trigger a special ability.'],
                        ['icon' => 'flash', 'title' => 'Deploy Special Abilities', 'desc' => 'Jump a player, ask a question, reverse direction, stack a penalty, or declare a new suit with an Ace.'],
                        ['icon' => 'plus-sign-circle', 'title' => 'No Valid Play? Draw a Card', 'desc' => 'If you can\'t match the suit, rank, or respond to an active effect, you must draw one card from the pile.'],
                    ] as $i => $step)
                        <div class="flex items-start gap-5 relative">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center text-[#f5c542] bg-gradient-to-b from-[#1a1200] to-[#0a0a0a] border-2 border-[#f5c542]/40 relative z-10"
                                 style="box-shadow: 0 0 20px rgba(245,197,66,0.2);">
                                @svg('hugeicons-'.$step['icon'], 'size-5')
                            </div>
                            <div class="glass-card glass-card-hover flex-1 p-5">
                                <h3 class="font-cinzel text-sm font-bold text-[#f5c542] uppercase tracking-wide mb-1.5">{{ $step['title'] }}</h3>
                                <p class="text-sm text-[#6b6b6b] leading-relaxed">{{ $step['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== WINNING THE GAME ===================== --}}
    <section id="winning" class="scroll-mt-36 py-16 md:py-20 bg-[#0a0a0a]">
        <div class="mx-auto max-w-5xl px-6">
            <div class="mb-12 text-center">
                <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Winning the Game</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Calling Kadi --}}
                <div class="glass-card p-8 relative overflow-hidden">
                    <div class="absolute top-4 right-4">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#f5c542]/10 border border-[#f5c542]/30 px-3 py-1 text-[10px] font-cinzel tracking-widest text-[#f5c542] animate-pulse">
                            ● LIVE ALERT
                        </span>
                    </div>
                    <div class="text-[#f5c542] mb-4">@svg('hugeicons-megaphone-01', 'size-9')</div>
                    <h3 class="font-cinzel font-bold text-lg text-[#f5c542] mb-3">The "Kadi!" Call</h3>
                    <p class="text-sm text-[#f5f5f0]/70 leading-relaxed">
                        The moment a player is down to one card, or one move from winning, the server automatically
                        announces <span class="text-[#f5c542] font-semibold">"Kadi!"</span> to the whole table. This gives every
                        opponent one last chance to disrupt or counter the impending victory before the final card lands.
                    </p>
                </div>

                {{-- Finishing Restrictions --}}
                <div class="glass-card p-8 relative overflow-hidden border-l-4 !border-l-[#ff6b6b]">
                    <div class="text-[#ff6b6b] mb-4">@svg('hugeicons-alert-01', 'size-9')</div>
                    <h3 class="font-cinzel font-bold text-lg text-[#ff6b6b] mb-3">Finishing Restrictions</h3>
                    <p class="text-sm text-[#f5f5f0]/70 leading-relaxed mb-4">
                        Your final card <span class="text-[#ff6b6b] font-semibold">must</span> be a standard Play Card
                        (4, 5, 6, 7, 9 or 10). Finish on any of these instead and you don't win:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['2', '3', 'Joker', '8', 'Queen', 'King', 'Jack', 'Ace'] as $card)
                            <span class="inline-flex items-center justify-center min-w-[2.5rem] rounded-lg border border-[#ff6b6b]/40 bg-[#ff6b6b]/10 px-2.5 py-1 text-xs font-cinzel font-bold text-[#ff6b6b]">
                                {{ $card }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Kadiless --}}
            <div class="mt-6 glass-card p-8 relative overflow-hidden border-l-4 !border-l-[#ff6b6b]">
                <div class="text-[#ff6b6b] mb-4">@svg('hugeicons-cancel-circle', 'size-9')</div>
                <h3 class="font-cinzel font-bold text-lg text-[#ff6b6b] mb-3">Going Kadiless</h3>
                <p class="text-sm text-[#f5f5f0]/70 leading-relaxed">
                    Land on a restricted card as your last move and you become <span class="text-[#ff6b6b] font-semibold">Kadiless</span>:
                    you don't win, and you also lose the ability to defend anyone else from winning. If another player is
                    already Kadiless, nobody at the table can win until the board clears.
                </p>
            </div>

            {{-- Final callout --}}
            <div class="mt-6 glass-card px-6 py-5 flex items-center gap-4">
                <div class="text-[#f5c542] flex-shrink-0">@svg('hugeicons-flag-01', 'size-6')</div>
                <p class="text-sm text-[#6b6b6b] leading-relaxed">
                    To secure the win, your winning card <em class="not-italic text-[#f5f5f0]">and</em> the board state
                    immediately before it must be entirely clear of restricted cards. A Joker can't be sitting on top of
                    the discard pile the moment before your last move. Play clean, play smart, and empty your hand.
                </p>
            </div>
        </div>
    </section>

    {{-- ===================== STRATEGY / ESSENCE OF KADI ===================== --}}
    <section id="strategy" class="scroll-mt-36 py-16 md:py-20 bg-[#0a0a0a]">
        <div class="mx-auto max-w-3xl px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-[#f5f5f0] mb-6" style="font-family: 'Cinzel', serif;">The Essence of Kadi</h2>
            <p class="font-cinzel text-xl md:text-2xl leading-snug text-[#f5c542] mb-6">
                Not a game of matching cards. A battle of strategy, timing and psychology.
            </p>
            <p class="text-sm md:text-base text-[#6b6b6b] leading-relaxed max-w-2xl mx-auto">
                The strongest players study their opponents: the cards they hesitate to play, the suits they avoid,
                the chains they're quietly building. Every move should have a purpose: know when to strike, when
                to hold back, and when to force a rival into an uncomfortable decision.
            </p>
            <p class="mt-6 text-sm md:text-base font-cinzel tracking-wide text-[#f5f5f0]">
                Outthink. Outplay. Outfox.
            </p>
        </div>
    </section>

    {{-- ===================== CLOSING CTA ===================== --}}
    <section class="py-16 md:py-20" style="background: linear-gradient(135deg, #1a1000, #2a1f00, #1a1000);">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <div class="mb-4 inline-flex rounded-full border border-[#f5c542]/40 bg-[#f5c542]/10 px-3 py-1 text-xs tracking-widest text-[#f5c542]">
                ♠ READY TO PLAY?
            </div>
            <h2 class="mb-3 text-3xl md:text-4xl font-bold text-[#f5c542]" style="font-family: 'Cinzel', serif;">
                You Know the Rules. Now Prove It.
            </h2>
            <p class="mb-8 text-[#f5f5f0]/60 max-w-lg mx-auto" style="font-family: 'Outfit', sans-serif;">
                Take your new knowledge to the table and challenge real opponents in competitive Kadi.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                @auth
                    <x-play-kadi class="btn-casino-primary inline-block rounded-full px-8 py-4 no-underline">
                        Play Kadi →
                    </x-play-kadi>
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
