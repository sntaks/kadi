{{-- Contents + search. Included twice (desktop sidebar, phone sheet); both share the legalDoc state. --}}
<div class="mb-3 flex items-center justify-between gap-3 pr-10 lg:pr-0">
    <p class="font-cinzel text-[11px] font-semibold uppercase tracking-[0.25em] text-[#f5c542]/80">Contents</p>
    <span class="text-[11px] tabular-nums text-zinc-500" x-show="progress > 0" x-cloak><span x-text="progress"></span>% read</span>
</div>

<label class="relative mb-2 block">
    <span class="sr-only">Search the {{ $noun }}</span>
    @svg('hugeicons-search-01', 'pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500', ['aria-hidden' => 'true'])
    <input type="search" x-model.debounce.200ms="query" @keydown.enter.prevent="goToFirstMatch()" @keydown.escape="query = ''"
           placeholder="Search, e.g. withdraw" autocomplete="off" enterkeyhint="search"
           class="w-full rounded-lg border border-white/10 bg-black/40 py-2.5 pr-3 pl-9 text-base text-[#f5f5f0] placeholder:text-zinc-500 focus:border-[#f5c542]/60 focus:outline-none lg:text-sm">
</label>

<p class="mb-2 min-h-4 text-xs text-zinc-400" aria-live="polite" x-text="searchSummary"></p>

<ol class="legal-toc-list relative -mx-1 min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-1 pb-1">
    @foreach ($document->sections as $section)
        <li x-show="matches('{{ $section['id'] }}')">
            <a href="#{{ $section['id'] }}" @click="go('{{ $section['id'] }}', $event)"
               :aria-current="active === '{{ $section['id'] }}' ? 'location' : null"
               :class="active === '{{ $section['id'] }}' ? 'bg-[#f5c542]/10 text-[#f5c542]' : 'text-[#f5f5f0]/70 hover:bg-white/[0.04] hover:text-[#f5f5f0]'"
               class="flex items-start gap-3 rounded-lg px-2.5 py-2 text-sm leading-snug transition focus-visible:outline-2 focus-visible:outline-[#f5c542]">
                <span class="w-5 shrink-0 pt-px text-[11px] font-semibold tabular-nums text-[#f5c542]/60">{{ sprintf('%02d', $section['number']) }}</span>
                <span class="flex-1">{{ $section['title'] }}</span>
                <span x-show="searching && hits('{{ $section['id'] }}') > 0" x-cloak
                      class="shrink-0 rounded-full bg-[#f5c542]/15 px-1.5 py-px text-[10px] font-semibold tabular-nums text-[#f5c542]"
                      x-text="hits('{{ $section['id'] }}')"></span>
            </a>
        </li>
    @endforeach
</ol>

<div x-show="searching && resultCount === 0" x-cloak class="py-3 text-sm text-zinc-400">
    Try a simpler word, such as
    <button type="button" class="text-[#f5c542] underline underline-offset-4" @click="query = 'refund'">refund</button>,
    <button type="button" class="text-[#f5c542] underline underline-offset-4" @click="query = 'phone'">phone</button> or
    <button type="button" class="text-[#f5c542] underline underline-offset-4" @click="query = 'delete'">delete</button>.
</div>
