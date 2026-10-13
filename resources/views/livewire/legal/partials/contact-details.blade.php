@php
    use App\Support\Contact;

    $legal = config('kadi.legal');
    $rows = array_filter([
        ['label' => 'Operator', 'value' => $legal['operator'] ?? null],
        ['label' => 'Website', 'value' => $legal['website'] ?? null, 'href' => $legal['website'] ?? null],
        ['label' => 'Email', 'value' => Contact::email(), 'href' => Contact::email() ? 'mailto:'.Contact::email() : null],
        ['label' => 'Customer care', 'value' => Contact::phone(), 'href' => Contact::phoneHref()],
        ['label' => 'WhatsApp', 'value' => Contact::whatsapp() ? 'Chat with us' : null, 'href' => Contact::whatsapp()],
        ['label' => 'Registered address', 'value' => $legal['registered_address'] ?? null],
        ['label' => 'Licence number', 'value' => $legal['licence_number'] ?? null],
    ], fn (array $row) => filled($row['value']));
@endphp

<dl class="legal-contact not-prose">
    @foreach ($rows as $row)
        <div class="legal-contact-row">
            <dt>{{ $row['label'] }}</dt>
            <dd>
                @if (! empty($row['href']))
                    <a href="{{ $row['href'] }}" @if (str_starts_with($row['href'], 'http')) target="_blank" rel="noopener" @endif>{{ $row['value'] }}</a>
                @else
                    {{ $row['value'] }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>
