{{-- Section header inside a page or panel: title, optional sub-line and actions. --}}
@props(['title', 'sub' => null, 'id' => null])
<div {{ $attributes->class('nt-sec-head') }}>
    <h2 @if ($id) id="{{ $id }}" @endif>{{ $title }}</h2>
    @if ($sub)<span class="nt-sub hidden sm:inline">{{ $sub }}</span>@endif
    @isset($actions)<div class="nt-actions">{{ $actions }}</div>@endisset
</div>
