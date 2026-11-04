{{--
    A help page in the reading column (plan §2 "Help pages in the shell", Phase 6b): page head, then the
    contents list (sticky beside the text on desktop, a row of chips on phones) and the text, at most
    68 characters wide. `toc` is a list of [id, label]; the section in view is marked as you read.
--}}
@props(['title', 'intro' => null, 'crumb' => 'Help', 'toc' => []])
<div class="nt-view-in">
    {{-- The page-head pattern, written out: an optional actions slot can't be passed through conditionally. --}}
    <header class="nt-page-head">
        <div class="min-w-0">
            @if ($crumb)<div class="nt-crumb">{{ $crumb }}</div>@endif
            <h1>{{ $title }}</h1>
            @if ($intro)<p>{{ $intro }}</p>@endif
        </div>
        @isset($actions)<div class="nt-actions">{{ $actions }}</div>@endisset
    </header>

    <div @class(["nt-read", "nt-read-solo" => $toc === []]) x-data="{
            active: @js($toc[0][0] ?? null),
            init() {
                const sections = [...this.$root.querySelectorAll('[data-help-section]')];
                if (! sections.length || ! ('IntersectionObserver' in window)) return;
                this.observer = new IntersectionObserver((entries) => {
                    const seen = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
                    if (seen) this.active = seen.target.id;
                }, { rootMargin: '-80px 0px -60% 0px' });
                sections.forEach((s) => this.observer.observe(s));
            },
            destroy() { this.observer?.disconnect(); },
         }">
        @if ($toc !== [])
            <nav class="nt-toc" aria-label="On this page">
                <b>On this page</b>
                @foreach ($toc as [$id, $label])
                    <a href="#{{ $id }}" x-bind:aria-current="active === @js($id) ? 'true' : null">{{ $label }}</a>
                @endforeach
            </nav>
        @endif
        <article class="nt-prose">
            {{ $slot }}
        </article>
    </div>
</div>
