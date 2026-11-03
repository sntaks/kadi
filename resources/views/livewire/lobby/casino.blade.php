<div class="nt-view-in">
    <x-ui.page-head crumb="Casino" :title="$label"
                    :description="$filter === 'all' ? 'Every slot and scratch card, played right here with your Kadi wallet.' : null" />

    <nav class="nt-tabs mb-5" aria-label="Casino shelves"
         x-init="$nextTick(() => { const a = $el.querySelector('[aria-current]'); if (a) $el.scrollLeft += a.getBoundingClientRect().left - $el.getBoundingClientRect().left - ($el.clientWidth - a.offsetWidth) / 2 })">
        @foreach ($filters as $key => $name)
            <a href="{{ $key === 'all' ? route('casino') : route('casino', ['filter' => $key]) }}" wire:navigate
               class="nt-tab" @if ($key === $filter) aria-current="page" @endif>{{ $name }}</a>
        @endforeach
    </nav>

    @if ($filter === 'favourites' && $guest)
        <div class="nt-empty">
            <b>Keep your favourite games in one place</b>
            Log in, then tap the heart on any slot or scratch card.
            <div class="mt-4"><x-ui.button :href="route('login')">Log in</x-ui.button></div>
        </div>
    @elseif ($titles->isEmpty())
        <div class="nt-empty">
            @if ($filter === 'favourites')
                <b>No favourites yet</b>
                Tap the heart on any slot or scratch card and it shows up here.
            @else
                <b>Nothing to play here right now</b>
                These games are taking a break. Try another shelf.
            @endif
        </div>
    @else
        <p class="mb-4 text-sm text-ash">{{ $titles->count() }} {{ Str::plural('game', $titles->count()) }}</p>
        <div class="nt-tile-grid">
            @foreach ($titles as $title)
                <x-lobby.tile :title="$title" :favourites="$favourites" wire:key="casino-{{ $title->code }}" />
            @endforeach
        </div>
    @endif
</div>
