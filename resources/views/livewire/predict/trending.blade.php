<section class="nt-panel nt-pm-shelf" aria-labelledby="pm-trending" data-predict-live>
    <x-ui.section-head id="pm-trending" title="Predictions" sub="Trending markets">
        <x-slot:actions>
            <a href="{{ route('predictions') }}" class="nt-btn nt-btn-quiet nt-btn-sm" wire:navigate>All markets</a>
        </x-slot:actions>
    </x-ui.section-head>
    @if ($markets === [])
        <div class="nt-empty"><b>No markets to show right now</b>Have a look in a minute.</div>
    @else
        <div class="nt-pm-shelf-row">
            @foreach ($markets as $market)
                <x-predict.market-card :$market :$now wire:key="pm-trend-{{ $market->id }}" />
            @endforeach
        </div>
    @endif
</section>
