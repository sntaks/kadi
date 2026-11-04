<div class="mx-auto max-w-7xl space-y-4"
     data-game-page
     x-data="gameFrame({ origins: @js($origins), fallbackAfter: {{ (int) config('games.launch.fallback_after_seconds', 8) }}, framed: @js($gameModel->playsInFrame()), lobby: @js(route('home')) })"
     :class="immersive && 'game-immersive'">

    {{-- Game bar --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-0">
            <p class="text-xs text-gray-500">{{ $crumb }}</p>
            <h1 class="truncate text-2xl font-bold text-[#f5f5f0]" style="font-family:'Outfit',sans-serif;">{{ $name }}</h1>
        </div>
        <div class="ml-auto flex w-full gap-2 sm:w-auto">
            @if ($gameModel->playsInFrame())
                <button type="button" @click="fullscreen()" class="btn-casino-ghost flex-1 rounded-xl px-4 py-2 text-sm sm:flex-none">Full screen</button>
            @endif
            <button type="button" @click="openInTab()" class="btn-casino-ghost hidden flex-1 rounded-xl px-4 py-2 text-sm sm:inline-block sm:flex-none">Open in its own tab</button>
            <a href="{{ route('home') }}" wire:navigate class="btn-casino-primary flex-1 rounded-xl px-4 py-2 text-center text-sm no-underline sm:flex-none">Back to lobby</a>
        </div>
    </div>

    {{-- The launch: posted into the frame (or a new tab). The page never holds a token. --}}
    <form x-ref="launch" method="POST" action="{{ route('play.launch', ['game' => $gameModel->code]) }}"
          target="{{ $gameModel->playsInFrame() ? 'game-frame' : '_self' }}" class="hidden">
        @csrf
        @if ($titleModel)
            <input type="hidden" name="product" value="{{ $titleModel->code }}">
        @endif
    </form>

    {{-- Frame --}}
    <div x-ref="stage" class="game-stage relative w-full overflow-hidden rounded-2xl border border-[#f5c542]/25 bg-black" data-game-stage>
        @if ($titleModel?->thumbnail)
            <img src="{{ $titleModel->thumbnail }}" alt="" class="absolute inset-0 h-full w-full scale-105 object-cover opacity-30" x-show="!loaded">
        @endif

        @if ($gameModel->playsInFrame())
            {{-- The frame has no src (the launch is posted into it), so a bare "fullscreen" would allow only this
                 site's origin. Each feature names the game servers instead, or the game cannot go full screen. --}}
            @php($frameAllow = collect(['fullscreen', 'autoplay', 'clipboard-write', 'screen-wake-lock'])->map(fn (string $feature) => trim($feature.' '.implode(' ', $origins)))->implode('; '))
            <iframe name="game-frame" x-ref="frame" title="{{ $name }}" @load="frameLoaded()"
                    allow="{{ $frameAllow }}" referrerpolicy="no-referrer"
                    class="absolute inset-0 h-full w-full border-0" :class="loaded ? 'opacity-100' : 'opacity-0'"></iframe>
        @endif

        <div class="absolute inset-0 grid place-items-center p-6 text-center" x-show="!loaded" x-transition.opacity>
            <div>
                <img src="/logo/kadi-card.png" alt="" class="mx-auto mb-3 h-16 w-16 animate-pulse">
                <p class="text-xl font-bold text-[#f5f5f0]" x-text="error ? @js($name).concat(' didn\'t open') : 'Opening '.concat(@js($name))"></p>
                <p class="mt-1 text-sm text-gray-400" x-show="error" x-text="error"></p>
                <div class="mx-auto mt-4 h-1.5 w-60 overflow-hidden rounded-full bg-white/10" x-show="!error">
                    <div class="h-full rounded-full bg-[#f5c542] transition-all duration-[1600ms]" :style="`width:${started ? 92 : 0}%`"></div>
                </div>
                <div class="mt-5 space-y-2" x-show="slow || error" x-cloak>
                    <p class="text-sm text-gray-400" x-show="slow && !error">Taking a while? The game can open in its own tab instead.</p>
                    <button type="button" @click="openInTab()" x-show="!error" class="btn-casino-primary rounded-xl px-5 py-2.5 text-sm">Open in its own tab</button>
                    <button type="button" @click="retry()" x-show="error" class="btn-casino-primary rounded-xl px-5 py-2.5 text-sm">Try again</button>
                </div>
            </div>
        </div>

        <a href="{{ route('home') }}" class="game-immersive-back absolute left-3 top-3 z-10 hidden rounded-full bg-black/70 px-3 py-1.5 text-sm text-[#f5f5f0]" x-show="immersive" @click.prevent="immersive = false">Exit full screen</a>
    </div>

    {{-- Session --}}
    <div class="grid grid-cols-2 overflow-hidden rounded-xl border border-[#f5c542]/15 bg-[#141414] text-sm sm:grid-cols-3">
        <div class="p-4"><span class="block text-xs text-gray-500">Wallet</span><span class="font-semibold text-[#f5f5f0]">One balance for every game</span></div>
        <div class="border-l border-[#f5c542]/15 p-4"><span class="block text-xs text-gray-500">This session</span><span class="font-semibold tabular-nums text-[#f5f5f0]" x-text="clock">0:00</span></div>
        <div class="col-span-2 border-t border-[#f5c542]/15 p-4 sm:col-span-1 sm:border-l sm:border-t-0"><span class="block text-xs text-gray-500">18+ only</span><span class="font-semibold text-[#f5f5f0]">Play with money you can afford to lose</span></div>
    </div>

    @if ($more !== [])
        <section aria-labelledby="more-title">
            <h2 id="more-title" class="mb-3 mt-2 text-lg font-bold text-[#f5f5f0]">More like this</h2>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                @foreach ($more as $other)
                    <a href="{{ route('play', ['game' => $other->game, 'title' => $other->slug()]) }}" class="group block min-w-0">
                        <div class="aspect-[2/3] overflow-hidden rounded-xl bg-[#1a1a1a]">
                            @if ($other->thumbnail)
                                <img src="{{ $other->thumbnail }}" alt="" loading="lazy" width="320" height="480" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @endif
                        </div>
                        <p class="mt-1.5 truncate text-xs font-semibold text-[#f5f5f0]">{{ $other->name }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
