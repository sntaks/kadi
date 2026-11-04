{{-- The FAQ in the reading column (Phase 6b). Same questions (App\Support\Faqs) and FAQPage structured data. --}}
<x-help.page title="Kadi FAQ" :toc="[]">
    <p>Quick answers about how to play Kadi online. For the full details, see the
        <a href="{{ route('rules') }}" wire:navigate>Kadi rules</a> and the
        <a href="{{ route('game-guide') }}" wire:navigate>games and prizes guide</a>.</p>

    <section aria-label="Questions">
        @foreach ($faqs as $faq)
            <details @if ($loop->first) open @endif>
                <summary>{{ $faq['q'] }}</summary>
                <p>{{ $faq['a'] }}</p>
            </details>
        @endforeach
    </section>

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
            ], $faqs),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
</x-help.page>
