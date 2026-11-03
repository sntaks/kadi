{{-- The one page header pattern: crumb, title, one-line description, optional actions. --}}
@props(['title', 'crumb' => null, 'description' => null])
<header {{ $attributes->class('nt-page-head') }}>
    <div class="min-w-0">
        @if ($crumb)<div class="nt-crumb">{{ $crumb }}</div>@endif
        <h1>{{ $title }}</h1>
        @if ($description)<p>{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="nt-actions">{{ $actions }}</div>@endisset
</header>
