{{-- Several markets of one event ("EPL: 2027 Champion", one market per team) as one card: the two likeliest with
     their chance and what Yes pays, the rest under "+N more" (a native disclosure, so it works without JavaScript). --}}
@props(['title', 'markets', 'now'])
@php
    $sorted = collect($markets)->sortByDesc(fn ($m) => $m->odds?->yesProbability ?? -1)->values();
    $top = $sorted->take(2);
    $rest = $sorted->slice(2);
    $first = $sorted->first();
    $left = \App\Predict\Format::timeLeft($first->bettingClosesAt, $now);
    $volume = $sorted->sum(fn ($m) => $m->odds?->isReference ? 0 : ($m->odds?->volume ?? 0));
    $allReference = $sorted->every(fn ($m) => $m->odds?->isReference);
    $labels = \App\Predict\Markets::outcomeLabels($sorted->all());
@endphp
<article {{ $attributes->class('nt-pm-card nt-pm-event-card') }} data-predict-event>
    <div class="nt-pm-card-head nt-pm-card-head-event">
        <x-predict.image :url="$first->imageUrl" />
        <h3 class="nt-pm-q">{{ $title }}</h3>
    </div>
    <ul class="nt-pm-rows">
        @foreach ($top as $market)
            <x-predict.event-row :$market :$now :label="$labels[$market->id]" />
        @endforeach
    </ul>
    @if ($rest->isNotEmpty())
        {{-- Every market of the event, for the sheet ("+N more"): already in the page, so it opens with no request. --}}
        <template data-event-rows>
            @foreach ($sorted as $market)
                <x-predict.event-row :$market :$now :label="$labels[$market->id]" />
            @endforeach
        </template>
        <button type="button" class="nt-pm-more" x-on:click="$openEvent(@js($title))">+{{ $rest->count() }} more</button>
    @endif
    <footer class="nt-pm-card-foot">
        <span>{{ $allReference ? 'Fixed odds' : ($volume > 0 ? \App\Predict\Format::kesShort($volume).' vol' : 'Estimated') }}</span>
        <span class="nt-num">{{ $sorted->count() }} markets</span>
        <span class="nt-pm-left nt-num">{{ $left ?? 'Closed' }}</span>
    </footer>
    @if ($reference = $sorted->first(fn ($m) => $m->odds?->isReference))
        <x-predict.attribution :odds="$reference->odds" />
    @endif
</article>
