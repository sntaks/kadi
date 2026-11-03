{{-- Site footer: who we are, 18+, the same links as the menu, and every way to reach support. --}}
@props(['nav'])
@php
    use App\Support\Contact;

    $contacts = array_values(array_filter([
        Contact::phone() ? ['href' => Contact::phoneHref(), 'label' => Contact::phone(), 'icon' => 'call'] : null,
        Contact::whatsapp() ? ['href' => Contact::whatsapp(), 'label' => 'WhatsApp', 'icon' => 'chat', 'external' => true] : null,
        Contact::email() ? ['href' => 'mailto:'.Contact::email(), 'label' => Contact::email(), 'icon' => 'mail'] : null,
    ]));
@endphp
{{-- On a gate (consent, choose a name) the footer is inert too: its Play Kadi can post off-site, where no gate runs. --}}
<footer @class(['nt-footer', 'nt-gated' => $nav->isGated()]) @if ($nav->isGated()) inert @endif>
    <div>
        <img src="{{ asset('logo/kadi-logo.webp') }}" alt="Kadi" width="228" height="96" loading="lazy">
        <p class="m-0 mb-3 max-w-[46ch]">Kenya's card game, head to head for real money. Stake only what you can afford to lose.</p>
        <p class="m-0"><span class="nt-age-badge">18+</span>Only for players aged 18 and over.</p>
        @if ($socials = Contact::socials())
            <ul class="nt-socials" aria-label="Follow Kadi">
                @foreach ($socials as $social)
                    <li>
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Kadi on {{ $social['label'] }} (opens in a new tab)">
                            <img src="{{ asset($social['icon']) }}" alt="" width="18" height="18" loading="lazy" class="m-0 size-[18px]">
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @foreach ($nav->footer() as $heading => $items)
        <div>
            <h2>{{ $heading }}</h2>
            <ul>
                @foreach ($items as $item)
                    <li><x-site.nav-item :item="$item" class="nt-footer-link" :icon="false" :label="$item['label'] === 'Kadi' ? 'Play Kadi' : null" /></li>
                @endforeach
            </ul>
            @if ($heading === 'Help' && $contacts !== [])
                <ul class="nt-contact mt-2">
                    @foreach ($contacts as $contact)
                        <li>
                            <a href="{{ $contact['href'] }}" @if (! empty($contact['external'])) target="_blank" rel="noopener noreferrer" @endif>
                                <x-site.icon :name="$contact['icon']" />{{ $contact['label'] }}
                                @if (! empty($contact['external']))<span class="sr-only">(opens in a new tab)</span>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach

    <div class="nt-legal">
        <span>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
        <span>Play responsibly.</span>
    </div>
</footer>
