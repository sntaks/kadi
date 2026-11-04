{{-- Terms and Privacy in the reading column (Phase 6b): the same document (App\Support\LegalDocument), its sections as the contents. --}}
@php
    use App\Support\LegalDocument;

    $docs = ['terms' => ['Terms & Conditions', 'legal.terms'], 'privacy' => ['Privacy Policy', 'legal.privacy']];
    $contact = view('livewire.legal.partials.contact-details')->render();
    $withContact = fn (string $html) => str_replace(LegalDocument::CONTACT_PLACEHOLDER, $contact, $html);
    $toc = array_map(fn ($section) => [$section['id'], $section['number'].'. '.$section['title']], $document->sections);
    $facts = array_filter([
        $document->effective() ? 'Effective '.$document->effective() : null,
        $document->updated() ? 'Updated '.$document->updated() : null,
        'About '.$document->readingMinutes.' min read',
    ]);
@endphp
<x-help.page :title="$document->title()" :intro="$tagline" :toc="$toc">
    <x-slot:actions>
        <nav class="nt-tabs" aria-label="Legal documents">
            @foreach ($docs as $key => [$label, $route])
                <a href="{{ route($route) }}" wire:navigate class="nt-tab" @if ($key === $current) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </x-slot:actions>

    <p class="nt-hint">{{ implode(' · ', $facts) }}</p>

    <section aria-labelledby="short-version">
        <h2 id="short-version">The short version</h2>
        <ul>
            @foreach ($keyPoints as $point)
                <li><a href="#section-{{ $point['section'] }}">{{ $point['title'] }}</a>: {{ $point['text'] }}</li>
            @endforeach
        </ul>
        <p class="nt-hint">Tap a point to read the full section.</p>
    </section>

    @if (trim(strip_tags($document->introHtml)) !== '')
        <section aria-label="Introduction">{!! $withContact($document->introHtml) !!}</section>
    @endif

    @foreach ($document->sections as $section)
        <section id="{{ $section['id'] }}" data-help-section aria-labelledby="{{ $section['id'] }}-title">
            <h2 id="{{ $section['id'] }}-title">{{ $section['number'] }}. {{ $section['title'] }}</h2>
            {!! $withContact($section['html']) !!}
        </section>
    @endforeach
</x-help.page>
