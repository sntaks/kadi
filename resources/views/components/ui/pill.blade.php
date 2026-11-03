{{-- Small status pill. tone: neutral | ok | warn | hot | gold. --}}
@props(['tone' => 'neutral'])
<span {{ $attributes->class(['nt-pill', 'nt-pill-'.$tone => $tone !== 'neutral']) }}>{{ $slot }}</span>
