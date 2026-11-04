{{-- The heading of a sign-in or gate panel: title and one line. --}}
@props(['title', 'description' => null])
<div class="grid gap-1">
    <h1 class="m-0 text-[26px] font-extrabold leading-tight text-ivory">{{ $title }}</h1>
    @if ($description)<p class="m-0 text-[15px] text-soft">{{ $description }}</p>@endif
    {{ $slot }}
</div>
