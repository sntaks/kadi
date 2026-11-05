{{-- The one page header pattern: crumb, title, one-line description, optional actions. `level` 2 inside a frame that
     already has the page's h1 (the account pages). --}}
@props(['title', 'crumb' => null, 'description' => null, 'level' => 1])
<header {{ $attributes->class('nt-page-head') }}>
    <div class="min-w-0">
        @if ($crumb)<div class="nt-crumb">{{ $crumb }}</div>@endif
        <h{{ (int) $level }}>{{ $title }}</h{{ (int) $level }}>
        @if ($description)<p>{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="nt-actions">{{ $actions }}</div>@endisset
</header>
