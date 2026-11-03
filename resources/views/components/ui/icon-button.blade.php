{{-- Square icon button. `label` is required: it is the only accessible name. `dot` marks something new. --}}
@props(['label', 'href' => null, 'dot' => false])
@if ($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" {{ $attributes->class('nt-icon-btn') }}>{{ $slot }}@if ($dot)<i class="nt-dot" aria-hidden="true"></i>@endif</a>
@else
    <button type="button" aria-label="{{ $label }}" {{ $attributes->class('nt-icon-btn') }}>{{ $slot }}@if ($dot)<i class="nt-dot" aria-hidden="true"></i>@endif</button>
@endif
