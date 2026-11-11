{{-- Line icons for the site shell (24px grid, currentColor). Decorative: the label beside them names the control. --}}
@props(['name'])
@php
    $paths = [
        'ball' => '<circle cx="12" cy="12" r="9"/><path d="m12 7 4 3-1.5 4.5h-5L8 10z"/><path d="M12 3v4M21 10.5l-5-.5M18 19l-3.5-4.5M6 19l3.5-4.5M3 10.5l5-.5"/>',
        'basketball' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3v18"/><path d="M5.8 5.8c2.5 2.6 2.5 9.8 0 12.4M18.2 5.8c-2.5 2.6-2.5 9.8 0 12.4"/>',
        'cards' => '<rect x="3.5" y="5" width="11" height="15" rx="2" transform="rotate(-8 9 12.5)"/><rect x="9.5" y="4" width="11" height="15" rx="2"/><path d="M15 8.5c-1.2 1.4-2 2.3-2 3.2a2 2 0 0 0 4 0c0-.9-.8-1.8-2-3.2Z"/>',
        // The card on the "i" of the Kadi logo: one playing card tilted 15°, a corner pip and a spade.
        'kadi' => '<g transform="rotate(15 12 12)"><rect x="6.2" y="3.2" width="11.6" height="17.6" rx="2.2"/><path d="M8.9 6.3v.01"/><path d="M12 8.7c-1.9 1.8-3.1 2.8-3.1 4.2a1.7 1.7 0 0 0 2.8 1.3l-.5 1.5h1.6l-.5-1.5a1.7 1.7 0 0 0 2.8-1.3c0-1.4-1.2-2.4-3.1-4.2Z"/></g>',
        // One poker chip from the front: rim, six edge notches, inner ring, centre diamond.
        'chip' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path d="M12 3v2.4M19.79 7.5l-2.07 1.2M19.79 16.5l-2.07-1.2M12 21v-2.4M4.21 16.5l2.07-1.2M4.21 7.5l2.07 1.2"/><path d="m12 10 2 2-2 2-2-2Z"/>',
        // A classic football: the centre pentagon, its seams and the neighbouring panels' edges.
        'football' => '<circle cx="12" cy="12" r="9"/><path d="M12 8.7l3.14 2.28-1.2 3.69h-3.88l-1.2-3.69Z"/><path d="M12 8.7V5.8M12 5.8 8.36 3.82M12 5.8l3.64-1.98M15.14 10.98l2.76-.9M17.9 10.08l.75-4.07M17.9 10.08l3 2.86M13.94 14.67l1.7 2.35M15.64 17.02l4.11-.55M15.64 17.02l-1.78 3.73M10.06 14.67l-1.7 2.35M8.36 17.02l1.78 3.73M8.36 17.02l-4.11-.55M8.86 10.98l-2.76-.9M6.1 10.08l-3 2.86M6.1 10.08l-.75-4.07"/>',
        'slot' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M9 5v14M15 5v14M5.5 12h1.5M11.2 12h1.6M17 12h1.5"/>',
        'wallet' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3"/><rect x="4" y="8" width="16" height="11" rx="2.5"/><path d="M16 13.5h1.5"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'invite' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M18.5 8v6M15.5 11h6"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0Z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4"/>',
        'live' => '<circle cx="12" cy="12" r="2.5"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M5 5a10 10 0 0 0 0 14M19 5a10 10 0 0 1 0 14"/>',
        'gift' => '<rect x="3.5" y="8.5" width="17" height="4" rx="1"/><path d="M5 12.5V20h14v-7.5M12 8.5V20M12 8.5S10.5 4 8 4.5 7 8.5 12 8.5ZM12 8.5S13.5 4 16 4.5s1 4-4 4Z"/>',
        'shield' => '<path d="M12 3 5 6v5.5c0 4.3 3 8 7 9.5 4-1.5 7-5.2 7-9.5V6Z"/><path d="m9 12 2 2 4-4"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/>',
        'chat' => '<path d="M20 11.5a8 8 0 0 1-11.7 7.1L4 20l1.4-4.1A8 8 0 1 1 20 11.5Z"/>',
        'doc' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/>',
        'bell' => '<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15Z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
        'out' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10"/>',
        'down' => '<path d="M12 4v12M7 11l5 5 5-5M5 20h14"/>',
        'home' => '<path d="m3.5 11 8.5-7 8.5 7"/><path d="M5.5 9.5V20h13V9.5M10 20v-5.5h4V20"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'chev' => '<path d="m6 9 6 6 6-6"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'slip' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2Z"/><path d="M9 8h6M9 12h6"/>',
        'phone' => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
        'call' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes }}>{!! $paths[$name] ?? '' !!}</svg>
