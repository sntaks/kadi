@props(['count' => 5])

{{-- List rows: a title and a meta line on the left, an amount and a pill on the right. --}}
<ul {{ $attributes->class('divide-y divide-line') }}>
    @for ($i = 0; $i < $count; $i++)
        <li class="flex items-center justify-between gap-3 py-3">
            <div class="min-w-0 flex-1 space-y-2">
                <x-skeleton :class="'h-4 max-w-full rounded '.['w-40', 'w-32', 'w-48'][$i % 3]" />
                <x-skeleton class="h-3 w-56 max-w-full rounded" />
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <x-skeleton class="h-4 w-16 rounded" />
                <x-skeleton class="h-7 w-16 rounded-full" />
            </div>
        </li>
    @endfor
</ul>
