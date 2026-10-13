@php
    use App\Support\Contact;
    use App\Support\LegalDocument;

    $docs = [
        'terms' => ['label' => 'Terms & Conditions', 'route' => 'legal.terms', 'noun' => 'terms'],
        'privacy' => ['label' => 'Privacy Policy', 'route' => 'legal.privacy', 'noun' => 'policy'],
    ];
    $other = $docs[$current === 'terms' ? 'privacy' : 'terms'];
    $noun = $docs[$current]['noun'];

    $contact = view('livewire.legal.partials.contact-details')->render();
    $withContact = fn (string $html) => str_replace(LegalDocument::CONTACT_PLACEHOLDER, $contact, $html);
@endphp

<div x-data="legalDoc" class="legal-doc bg-[#0a0a0a]">
    {{-- ===================== HERO ===================== --}}
    <section class="relative overflow-hidden pt-28 pb-10 md:pt-32 md:pb-14">
        <div class="pointer-events-none absolute top-1/2 left-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full"
             style="background: radial-gradient(circle, rgba(245,197,66,0.08) 0%, transparent 70%);"></div>

        <div class="relative z-10 mx-auto max-w-4xl px-4 text-center sm:px-6">
            <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-[#f5c542]/20 bg-[#f5c542]/10 px-4 py-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-[#f5c542]"></span>
                <span class="font-cinzel text-[10px] font-semibold uppercase tracking-[0.2em] text-[#f5c542]">Legal · Kadi Online</span>
            </div>

            <h1 class="font-cinzel mb-3 text-3xl font-black uppercase leading-tight tracking-wide text-[#f5c542] md:text-5xl"
                style="text-shadow: 0 0 30px rgba(245,197,66,0.35);">
                {{ $document->title() }}
            </h1>
            <p class="mx-auto max-w-xl text-sm leading-relaxed text-zinc-400 md:text-base">{{ $tagline }}</p>

            {{-- Switch between the two documents: one tap, always visible. --}}
            <nav aria-label="Legal documents" class="mt-7 inline-flex rounded-full border border-[#f5c542]/20 bg-white/[0.03] p-1">
                @foreach ($docs as $key => $doc)
                    <a href="{{ route($doc['route']) }}" wire:navigate
                       @if ($key === $current) aria-current="page" @endif
                       class="rounded-full px-4 py-2 text-xs font-semibold transition sm:px-5 sm:text-sm {{ $key === $current ? 'bg-[#f5c542] text-[#0a0a0a] shadow-[0_0_20px_rgba(245,197,66,0.3)]' : 'text-[#f5f5f0]/70 hover:text-[#f5c542]' }}">
                        {{ $doc['label'] }}
                    </a>
                @endforeach
            </nav>

            <dl class="mt-6 flex flex-wrap items-center justify-center gap-2 text-xs text-[#f5f5f0]/70">
                @if ($document->effective())
                    <div class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5">
                        <dt class="inline text-zinc-500">Effective</dt> <dd class="inline">{{ $document->effective() }}</dd>
                    </div>
                @endif
                @if ($document->updated())
                    <div class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5">
                        <dt class="inline text-zinc-500">Updated</dt> <dd class="inline">{{ $document->updated() }}</dd>
                    </div>
                @endif
                <div class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1.5">
                    <dt class="sr-only">Reading time</dt> <dd class="inline">About {{ $document->readingMinutes }} min read</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ===================== THE SHORT VERSION ===================== --}}
    <section aria-labelledby="short-version" class="pb-10 md:pb-14">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="glass-card p-5 sm:p-8">
                <div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="font-cinzel text-[10px] uppercase tracking-[0.25em] text-[#f5c542]/70">In plain words</p>
                        <h2 id="short-version" class="font-cinzel text-xl font-bold text-[#f5f5f0] md:text-2xl">The short version</h2>
                    </div>
                    <p class="text-xs text-zinc-500">Tap a point to read the full section.</p>
                </div>

                {{-- Phones show compact rows and the first 4 points (the rest behind "Show all"), so the full text is not 8 cards away. --}}
                <ul x-data="{ all: false }" class="grid gap-2.5 sm:grid-cols-2 sm:gap-3 lg:grid-cols-4">
                    @foreach ($keyPoints as $point)
                        <li @if ($loop->index >= 4) :class="all ? '' : 'max-sm:hidden'" @endif>
                            <a href="#section-{{ $point['section'] }}" @click="go('section-{{ $point['section'] }}', $event)"
                               class="group flex h-full items-start gap-3 rounded-xl border border-white/[0.06] bg-black/30 p-3.5 transition hover:border-[#f5c542]/40 hover:bg-[#f5c542]/[0.04] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5c542] sm:flex-col sm:gap-0 sm:p-4">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-[#f5c542]/25 bg-[#f5c542]/10 text-[#f5c542] sm:mb-3">
                                    @svg('hugeicons-'.$point['icon'], 'size-5', ['aria-hidden' => 'true'])
                                </span>
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="font-cinzel mb-1 text-sm font-bold text-[#f5c542]">{{ $point['title'] }}</span>
                                    <span class="flex-1 text-sm leading-relaxed text-zinc-300">{{ $point['text'] }}</span>
                                    <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[#f5c542]/70 transition group-hover:text-[#f5c542] sm:mt-3">
                                        Read section {{ $point['section'] }}
                                        @svg('hugeicons-arrow-right-01', 'size-3.5 transition-transform group-hover:translate-x-0.5', ['aria-hidden' => 'true'])
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                    @if (count($keyPoints) > 4)
                        <li x-show="! all" class="sm:hidden">
                            <button type="button" @click="all = true"
                                    class="w-full rounded-xl border border-dashed border-[#f5c542]/30 py-3 text-sm font-semibold text-[#f5c542] transition hover:bg-[#f5c542]/[0.06]">
                                Show all {{ count($keyPoints) }} key points
                            </button>
                        </li>
                    @endif
                </ul>

                <p class="mt-5 flex items-start gap-2 text-xs leading-relaxed text-zinc-500">
                    @svg('hugeicons-information-circle', 'mt-px size-4 shrink-0 text-[#f5c542]/60', ['aria-hidden' => 'true'])
                    <span>This summary helps you find your way around. It does not replace the full {{ $noun }} below, which is what applies.</span>
                </p>
            </div>
        </div>
    </section>

    {{-- ===================== FULL TEXT ===================== --}}
    <div class="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-12">
        {{-- Phones/tablets: a sticky bar that always says where you are and opens the contents. --}}
        <div class="legal-mobile-bar sticky z-30 -mx-4 sm:-mx-6 lg:hidden">
            <button type="button" @click="openContents()"
                    class="flex w-full items-center gap-3 border-y border-[#f5c542]/15 bg-[#0a0a0a]/95 px-4 py-2.5 text-left backdrop-blur-xl sm:px-6"
                    aria-haspopup="dialog">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#f5c542]/25 text-[#f5c542]" aria-hidden="true">
                    @svg('hugeicons-left-to-right-list-bullet', 'size-4')
                </span>
                <span class="min-w-0 flex-1">
                    <span class="font-cinzel block text-[10px] uppercase tracking-[0.2em] text-[#f5c542]/70">
                        Contents<span x-show="progress > 0" x-cloak> · <span x-text="progress"></span>% read</span>
                    </span>
                    <span class="block truncate text-sm text-[#f5f5f0]" x-text="activeLabel">Jump to a section</span>
                </span>
                @svg('hugeicons-arrow-down-01', 'size-4 shrink-0 text-[#f5f5f0]/50', ['aria-hidden' => 'true'])
            </button>
            <div class="absolute bottom-0 left-0 h-0.5 bg-[#f5c542] transition-[width] duration-150" :style="`width: ${progress}%`" aria-hidden="true"></div>
        </div>

        {{-- Desktop: contents always on screen. --}}
        <aside class="hidden lg:block" aria-label="Contents">
            <div class="glass-card sticky top-24 flex max-h-[calc(100vh-7.5rem)] flex-col p-4">
                @include('livewire.legal.partials.contents')
            </div>
        </aside>

        <article x-ref="article" class="min-w-0 pt-8 lg:pt-0">
            @if ($document->introHtml !== '')
                <div class="legal-prose legal-intro">{!! $withContact($document->introHtml) !!}</div>
            @endif

            @foreach ($document->sections as $section)
                <section id="{{ $section['id'] }}" data-legal-section data-label="{{ $section['number'] }}. {{ $section['title'] }}"
                         aria-labelledby="{{ $section['id'] }}-title" class="legal-section">
                    <header class="mb-5 flex items-start gap-3">
                        <span class="legal-section-no" aria-hidden="true">{{ sprintf('%02d', $section['number']) }}</span>
                        <h2 id="{{ $section['id'] }}-title" tabindex="-1" class="font-cinzel flex-1 pt-1 text-lg font-bold leading-snug text-[#f5f5f0] outline-none md:text-xl">
                            <span class="sr-only">Section {{ $section['number'] }}: </span>{{ $section['title'] }}
                        </h2>
                        <button type="button" @click="copyLink('{{ $section['id'] }}')"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#f5f5f0]/40 transition hover:bg-white/[0.05] hover:text-[#f5c542] focus-visible:outline-2 focus-visible:outline-[#f5c542]"
                                title="Copy a link to this section" aria-label="Copy a link to section {{ $section['number'] }}">
                            <span x-show="copied !== '{{ $section['id'] }}'" aria-hidden="true">@svg('hugeicons-link-01', 'size-4')</span>
                            <span x-show="copied === '{{ $section['id'] }}'" x-cloak class="text-[#4ade80]" aria-hidden="true">@svg('hugeicons-tick-02', 'size-4')</span>
                        </button>
                    </header>
                    <div class="legal-prose">{!! $withContact($section['html']) !!}</div>
                </section>
            @endforeach

            {{-- ===================== HELP ===================== --}}
            <div class="glass-card mt-12 p-6 text-center sm:p-8" style="background: linear-gradient(135deg, rgba(245,197,66,0.08), rgba(255,255,255,0.02));">
                <p class="font-cinzel mb-1 text-[10px] uppercase tracking-[0.25em] text-[#f5c542]/70">Need help?</p>
                <h2 class="font-cinzel mb-2 text-xl font-bold text-[#f5c542] md:text-2xl">Something not clear?</h2>
                <p class="mx-auto mb-6 max-w-md text-sm leading-relaxed text-zinc-400">
                    Ask us before you play. Mention the section number and we will point you to the answer.
                </p>
                <div class="flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    @if (Contact::whatsapp())
                        <a href="{{ Contact::whatsapp() }}" target="_blank" rel="noopener" class="btn-casino-primary inline-block rounded-full px-6 py-3 text-sm no-underline">Chat on WhatsApp</a>
                    @endif
                    @if (Contact::phone())
                        <a href="{{ Contact::phoneHref() }}" class="btn-casino-ghost inline-block rounded-full px-6 py-3 text-sm no-underline">Call {{ Contact::phone() }}</a>
                    @endif
                    @if (Contact::email())
                        <a href="mailto:{{ Contact::email() }}" class="btn-casino-ghost inline-block rounded-full px-6 py-3 text-sm no-underline">Email us</a>
                    @endif
                </div>
                <p class="mt-6 text-sm text-zinc-400">
                    Also read our
                    <a href="{{ route($other['route']) }}" wire:navigate class="text-[#f5c542] underline underline-offset-4 hover:text-[#ffde74]">{{ $other['label'] }}</a>
                    and the
                    <a href="{{ route('rules') }}" wire:navigate class="text-[#f5c542] underline underline-offset-4 hover:text-[#ffde74]">game rules</a>.
                </p>
            </div>
        </article>
    </div>

    {{-- Contents sheet for phones and tablets (slides up from the bottom). --}}
    <flux:modal name="legal-contents" class="kadi-bottom-sheet w-full sm:max-w-md">
        <div class="flex max-h-[calc(min(85dvh,40rem)-3.5rem)] flex-col pt-2">
            @include('livewire.legal.partials.contents')
        </div>
    </flux:modal>

    {{-- Back to top --}}
    <button type="button" x-show="showTop" x-cloak x-transition.opacity @click="toTop()"
            class="legal-to-top fixed right-4 z-30 flex h-11 w-11 items-center justify-center rounded-full border border-[#f5c542]/30 bg-[#111]/90 text-[#f5c542] shadow-lg backdrop-blur transition hover:border-[#f5c542] lg:right-8"
            aria-label="Back to top">
        @svg('hugeicons-arrow-up-01', 'size-5', ['aria-hidden' => 'true'])
    </button>

    {{-- "Link copied" toast --}}
    <div x-show="copied" x-cloak x-transition.opacity role="status"
         class="legal-toast fixed left-1/2 z-50 -translate-x-1/2 rounded-full border border-[#4ade80]/30 bg-[#111] px-4 py-2 text-sm text-[#f5f5f0] shadow-lg">
        Link copied. Paste it to share this section.
    </div>
</div>
